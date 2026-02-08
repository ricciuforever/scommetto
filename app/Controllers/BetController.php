<?php
// app/Controllers/BetController.php

namespace App\Controllers;

use App\Models\Bet;
use App\Services\BetfairService;
use App\Config\Config;

class BetController
{
    private $betModel;

    public function __construct()
    {
        $this->betModel = new Bet();
    }

    public function getHistory()
    {
        header('Content-Type: application/json');
        $db = \App\Services\Database::getInstance()->getConnection();
        try {
            $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                    FROM bets b
                    LEFT JOIN fixtures f ON b.fixture_id = f.id
                    LEFT JOIN leagues l ON f.league_id = l.id
                    LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                    ORDER BY b.timestamp DESC LIMIT 1000";
            $history = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($history);
        } catch (\Throwable $e) {
            echo json_encode([]);
        }
    }

    public function placeBet()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['fixture_id'])) {
            echo json_encode(['error' => 'Invalid input']);
            return;
        }

        if ($this->betModel->isPending($input['fixture_id'])) {
            echo json_encode(['status' => 'already_exists']);
            return;
        }

        $stake = (float) ($input['stake'] ?? 0);
        $confidence = (int) ($input['confidence'] ?? 0);
        $threshold = Config::BETFAIR_CONFIDENCE_THRESHOLD;

        $betfairId = null;
        $status = 'pending';
        $note = '';
        $logMsg = "Manuale: Fixture {$input['fixture_id']}, Conf: $confidence% (Threshold: $threshold%). ";

        try {
            $bf = new BetfairService();
            $isSimulation = Config::isSimulationMode();
            $logMsg .= $isSimulation ? "[SIMULAZIONE] " : "[REAL] ";

            if ($bf->isConfigured() && $confidence >= $threshold && !$isSimulation) {
                $logMsg .= "Procedo su Betfair. ";

                $fixtureId = (int) $input['fixture_id'];
                $db = \App\Services\Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT f.*, t1.name as home_name, t2.name as away_name
                                     FROM fixtures f
                                     JOIN teams t1 ON f.team_home_id = t1.id
                                     JOIN teams t2 ON f.team_away_id = t2.id
                                     WHERE f.id = ?");
                $stmt->execute([$fixtureId]);
                $fixture = $stmt->fetch(\PDO::FETCH_ASSOC);

                $matchName = $input['match_name'] ?? ($input['match'] ?? '');
                if (!$matchName && $fixture) {
                    $matchName = $fixture['home_name'] . ' vs ' . $fixture['away_name'];
                }
                if (!$matchName && isset($input['home_team']) && isset($input['away_team'])) {
                    $matchName = $input['home_team'] . ' v ' . $input['away_team'];
                }

                if ($matchName) {
                    $advice = $input['prediction'] ?? $input['advice'] ?? '';
                    $market = $bf->findMarket($matchName, $advice);
                    if ($market) {
                        $selectionId = $bf->mapAdviceToSelection($advice, $market['runners'], $fixture['home_name'] ?? '', $fixture['away_name'] ?? '');
                        if ($selectionId) {
                            $price = (float) ($input['odds'] ?? 1.01);
                            $order = $bf->placeBet($market['marketId'], $selectionId, $price, $stake);

                            // Gestione flessibile risposta Betfair (REST o RPC)
                            $res = isset($order['status']) ? $order : ($order['result'] ?? null);

                            if ($res && $res['status'] === 'SUCCESS') {
                                $reports = $res['instructionReports'] ?? ($res['result']['instructionReports'] ?? []);
                                $betfairId = $reports[0]['betId'] ?? null;
                                $status = 'placed';
                                $note .= "[BETFAIR] Scommessa piazzata! ID: $betfairId. ";
                            } else {
                                $note .= "[BETFAIR ERROR] " . json_encode($order);
                            }
                        } else {
                            $note .= "[BETFAIR] Selezione non trovata. ";
                        }
                    } else {
                        $note .= "[BETFAIR] Mercato non trovato. ";
                    }
                }
            } else {
                $logMsg .= "Soglia non raggiunta, non configurato, o modo simulazione. ";
                // In simulazione, settiamo betfair_id a NULL ma status a 'placed' se confidence OK
                if ($isSimulation && $confidence >= 60) {
                    $status = 'placed';
                    $note .= "[SIMULAZIONE] Scommessa virtuale piazzata.";
                }
            }
        } catch (\Throwable $e) {
            $note .= "[BETFAIR EXCEPTION] " . $e->getMessage();
        }

        error_log("[BET_CONTROLLER] " . $logMsg . " Note: $note");

        if ($betfairId)
            $input['betfair_id'] = $betfairId;
        $input['status'] = $status;
        $input['notes'] = ($input['notes'] ?? '') . ' ' . $note;

        $id = $this->betModel->create($input);
        echo json_encode(['status' => 'success', 'id' => $id, 'betfair_notes' => $note]);
    }

    public function viewTracker()
    {
        try {
            // Versione semplificata: Il rendering dei movimenti Betfair è gestito da JS in app.js
            require __DIR__ . '/../Views/partials/tracker.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function getRealBalance()
    {
        header('Content-Type: application/json');

        // --- SIMULATION MODE BALANCE ---
        if (Config::isSimulationMode()) {
            try {
                $db = \App\Services\Database::getInstance()->getConnection();

                // 1. Calcola P&L dalle scommesse chiuse (won/lost) simulate (betfair_id IS NULL)
                $stmt = $db->query("SELECT status, odds, stake FROM bets WHERE betfair_id IS NULL AND status IN ('won', 'lost')");
                $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $profit = 0;
                foreach ($history as $bet) {
                    if ($bet['status'] === 'won') {
                        $profit += $bet['stake'] * ($bet['odds'] - 1);
                    } else {
                        $profit -= $bet['stake'];
                    }
                }

                // 2. Calcola Esposizione (scommesse piazzate/pending)
                $stmt = $db->query("SELECT SUM(stake) as exposure FROM bets WHERE betfair_id IS NULL AND status IN ('placed', 'pending')");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $exposure = (float) ($row['exposure'] ?? 0);

                $initial = Config::INITIAL_BANKROLL;
                $totalEquity = $initial + $profit;
                $available = $totalEquity - $exposure;

                echo json_encode([
                    'available' => $available,
                    'exposure' => $exposure,
                    'wallet' => $totalEquity,
                    'currency' => 'EUR',
                    'wallet_name' => 'Conto Virtuale'
                ]);
            } catch (\Throwable $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        // --- REAL BETFAIR BALANCE ---
        try {
            $bf = new BetfairService();
            if (!$bf->isConfigured()) {
                echo json_encode(['error' => 'Not configured']);
                return;
            }
            $funds = $bf->getFunds();
            $data = $funds['result'] ?? $funds;
            if (isset($data['availableToBetBalance'])) {
                echo json_encode([
                    'available' => $data['availableToBetBalance'],
                    'exposure' => $data['exposure'],
                    'wallet' => $data['availableToBetBalance'] + abs($data['exposure']),
                    'currency' => $data['currencyCode'] ?? 'EUR',
                    'wallet_name' => $data['wallet'] ?? 'Betfair Real'
                ]);
            } else {
                echo json_encode(['error' => 'API Error', 'details' => $funds]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getOrders()
    {
        header('Content-Type: application/json');
        try {
            $bf = new BetfairService();
            if (!$bf->isConfigured()) {
                echo json_encode(['response' => []]);
                return;
            }
            $res = $bf->getCurrentOrders();
            echo json_encode(['response' => $res['currentOrders'] ?? []]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

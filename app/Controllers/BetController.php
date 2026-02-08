<?php
// app/Controllers/BetController.php

namespace App\Controllers;

use App\Models\Bet;

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
            $history = $db->query("SELECT * FROM bets ORDER BY timestamp DESC LIMIT 1000")->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($history);
        } catch (\Throwable $e) { echo json_encode([]); }
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
        $threshold = \App\Config\Config::BETFAIR_CONFIDENCE_THRESHOLD;

        $betfairId = null;
        $status = 'pending';
        $note = '';
        $logMsg = "Manuale: Fixture {$input['fixture_id']}, Conf: $confidence% (Threshold: $threshold%). ";

        try {
            $bf = new \App\Services\BetfairService();

            if ($bf->isConfigured() && $confidence >= $threshold) {
                $logMsg .= "Procedo su Betfair. ";
                $matchName = $input['match_name'] ?? ($input['match'] ?? '');
                if (!$matchName && isset($input['home_team']) && isset($input['away_team'])) {
                    $matchName = $input['home_team'] . ' v ' . $input['away_team'];
                }

                if ($matchName) {
                    $market = $bf->findMarket($matchName);
                    if ($market) {
                        $selectionId = $bf->mapAdviceToSelection($input['prediction'] ?? $input['advice'] ?? '', $market['runners']);
                        if ($selectionId) {
                            $order = $bf->placeBet($market['marketId'], $selectionId, (float)($input['odds'] ?? 1.01), $stake);
                            $res = isset($order['status']) ? $order : ($order['result'] ?? null);

                            if ($res && $res['status'] === 'SUCCESS') {
                                $betfairId = $res['instructionReports'][0]['betId'] ?? null;
                                $status = 'placed';
                                $note .= "[BETFAIR] Scommessa piazzata con successo. ";
                            } else {
                                $note .= "[BETFAIR ERROR] " . json_encode($order);
                            }
                        } else { $note .= "[BETFAIR] Selezione non trovata. "; }
                    } else { $note .= "[BETFAIR] Mercato non trovato. "; }
                }
            } else { $logMsg .= "Soglia non raggiunta o non configurato. "; }
        } catch (\Throwable $e) { $note .= "[BETFAIR EXCEPTION] " . $e->getMessage(); }

        error_log("[BET_CONTROLLER] " . $logMsg . " Note: $note");

        if ($betfairId) $input['betfair_id'] = $betfairId;
        $input['status'] = $status;
        $input['notes'] = ($input['notes'] ?? '') . ' ' . $note;

        $id = $this->betModel->create($input);
        echo json_encode(['status' => 'success', 'id' => $id, 'betfair_notes' => $note]);
    }

    public function viewTracker()
    {
        try {
            $status = $_GET['status'] ?? 'all';
            $db = \App\Services\Database::getInstance()->getConnection();
            $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                    FROM bets b
                    LEFT JOIN fixtures f ON b.fixture_id = f.id
                    LEFT JOIN leagues l ON f.league_id = l.id
                    LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                    ORDER BY b.timestamp DESC LIMIT 1000";
            $allBets = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            $balanceSummary = $this->betModel->getBalanceSummary(\App\Config\Config::INITIAL_BANKROLL);
            $bets = array_filter($allBets, function ($bet) use ($status) {
                if ($status === 'all') return true;
                return ($bet['status'] ?? '') === $status;
            });

            require __DIR__ . '/../Views/partials/tracker.php';
        } catch (\Throwable $e) { echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>'; }
    }

    public function getRealBalance()
    {
        header('Content-Type: application/json');
        try {
            $bf = new \App\Services\BetfairService();
            if (!$bf->isConfigured()) { echo json_encode(['error' => 'Not configured']); return; }
            $funds = $bf->getFunds();
            $data = $funds['result'] ?? $funds;
            if (isset($data['availableToBetBalance'])) {
                echo json_encode([
                    'available' => $data['availableToBetBalance'],
                    'exposure' => $data['exposure'],
                    'wallet' => $data['availableToBetBalance'] + abs($data['exposure']),
                    'currency' => $data['currencyCode'] ?? 'EUR',
                    'wallet_name' => $data['wallet'] ?? 'Unknown'
                ]);
            } else { echo json_encode(['error' => 'API Error', 'details' => $funds]); }
        } catch (\Throwable $e) { echo json_encode(['error' => $e->getMessage()]); }
    }
}

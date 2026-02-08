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
        if (!$input) {
            $input = $_POST;
        }

        if (!$input || !isset($input['fixture_id'])) {
            echo json_encode(['error' => 'Invalid input', 'received' => $input]);
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
        
        if(isset($_SERVER['HTTP_HX_REQUEST'])) {
            echo '<div class="p-10 text-center"><h3 class="text-3xl font-black text-success uppercase italic mb-4">Scommessa Piazzata!</h3><p class="text-slate-400 mb-8">La tua giocata è stata registrata con successo.</p><button onclick="document.getElementById(\'place-bet-modal\').remove()" class="bg-white/10 hover:bg-white/20 px-8 py-3 rounded-xl text-white font-bold uppercase transition-all">Chiudi</button></div>';
        } else {
            echo json_encode(['status' => 'success', 'id' => $id, 'betfair_notes' => $note]);
        }
    }

    public function viewTracker()
    {
        try {
            $status = $_GET['status'] ?? 'all';
            $db = \App\Services\Database::getInstance()->getConnection();

            // 1. Fetch Bets
            $sql = "SELECT b.*, f.name as match_name_fixture 
                    FROM bets b 
                    LEFT JOIN fixtures f ON b.fixture_id = f.id 
                    WHERE 1=1";
            $params = [];

            if ($status !== 'all') {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY b.timestamp DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $bets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Normalize match name
            foreach ($bets as &$b) {
                if (empty($b['match_name']) && !empty($b['match_name_fixture'])) {
                    $b['match_name'] = $b['match_name_fixture'];
                }
            }
            unset($b);

            // 2. Calculate Balance & Stats (Unified Logic)
            $statsSummary = [
                'available_balance' => 0,
                'currentPortfolio' => 0,
                'netProfit' => 0,
                'roi' => 0,
                'winCount' => 0,
                'lossCount' => 0
            ];

            // ROI & Profit from history
            $totalStake = 0;
            $totalReturn = 0;

            // Re-fetch all for stats if filtered
            $allBetsStmt = $db->query("SELECT status, stake, odds, betfair_id FROM bets");
            $allBets = $allBetsStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate ROI/Profit based on Mode (Sim/Real separation in history? 
            // Currently dashboard mixes them or filters. Let's assume we show ALL local history stats
            // but Balance reflects current mode.)

            // To be precise: If Sim Mode, show Sim Balance. If Real, Real Balance.
            // But History usually mixes unless we filter by `betfair_id` presence.
            // Let's stick to showing ALL DB history for ROI, but Mode-Specific Balance.

            foreach ($allBets as $b) {
                if ($b['status'] === 'won') {
                    $profit = $b['stake'] * ($b['odds'] - 1);
                    $statsSummary['netProfit'] += $profit;
                    $totalReturn += ($b['stake'] + $profit);
                    $statsSummary['winCount']++;
                } elseif ($b['status'] === 'lost') {
                    $statsSummary['netProfit'] -= $b['stake'];
                    $statsSummary['lossCount']++;
                }
                if (in_array($b['status'], ['won', 'lost'])) {
                    $totalStake += $b['stake'];
                }
            }

            if ($totalStake > 0) {
                $statsSummary['roi'] = ($statsSummary['netProfit'] / $totalStake) * 100;
            }

            // Balance
            if (Config::isSimulationMode()) {
                // Sim Logic
                $initial = Config::INITIAL_BANKROLL;

                // Recalculate Sim-Only Profit
                $simProfit = 0;
                $simExposure = 0;
                foreach ($allBets as $b) {
                    if (empty($b['betfair_id'])) { // Sim Bet
                        if ($b['status'] === 'won')
                            $simProfit += $b['stake'] * ($b['odds'] - 1);
                        elseif ($b['status'] === 'lost')
                            $simProfit -= $b['stake'];
                        elseif (in_array($b['status'], ['placed', 'pending']))
                            $simExposure += $b['stake'];
                    }
                }

                $statsSummary['currentPortfolio'] = $simExposure;
                $statsSummary['available_balance'] = ($initial + $simProfit) - $simExposure;
                // Override NetProfit to show Sim Profit in Sim Mode? Or Global? 
                // Let's show Global Profit in history, but Sim Balance.
            } else {
                // Real Logic
                try {
                    $bf = new BetfairService();
                    if ($bf->isConfigured()) {
                        $funds = $bf->getFunds();
                        if (isset($funds['result']))
                            $funds = $funds['result'];
                        $statsSummary['available_balance'] = $funds['availableToBetBalance'] ?? 0;
                        $statsSummary['currentPortfolio'] = abs($funds['exposure'] ?? 0);
                    }
                } catch (\Throwable $e) {
                }
            }

            require __DIR__ . '/../Views/partials/tracker.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore Tracker: ' . $e->getMessage() . '</div>';
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

    public function viewBetModal($id)
    {
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT b.*, f.name as match_name_fixture 
                                  FROM bets b 
                                  LEFT JOIN fixtures f ON b.fixture_id = f.id 
                                  WHERE b.id = ?");
            $stmt->execute([$id]);
            $bet = $stmt->fetch(\PDO::FETCH_ASSOC);

            if($bet) {
                if(empty($bet['match_name']) && !empty($bet['match_name_fixture'])) {
                    $bet['match_name'] = $bet['match_name_fixture'];
                }
                require __DIR__ . '/../Views/partials/modals/bet_details.php';
            } else {
                echo '<div class="text-white">Scommessa non trovata</div>';
            }
        } catch (\Throwable $e) {
            echo '<div class="text-danger">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewPlaceBetModal()
    {
        $fixtureId = $_GET['fixture_id'] ?? 0;
        $market = $_GET['market'] ?? '';
        $selection = $_GET['selection'] ?? '';
        $odd = $_GET['odd'] ?? 0;
        
        // Load fixture details for display
        $db = \App\Services\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT name FROM fixtures WHERE id = ?");
        $stmt->execute([$fixtureId]);
        $fixture = $stmt->fetch(\PDO::FETCH_ASSOC);
        $eventName = $fixture['name'] ?? 'Evento Sconosciuto';

        require __DIR__ . '/../Views/partials/modals/place_bet.php';
    }
}

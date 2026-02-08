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
            // Proviamo la query più semplice possibile per recuperare tutto
            $history = $db->query("SELECT * FROM bets ORDER BY timestamp DESC LIMIT 1000")->fetchAll(\PDO::FETCH_ASSOC);

            // Proviamo ad aggiungere le info extra solo se la query join non fallisce
            try {
                $sqlExtra = "SELECT b.id, l.country_name as country, bk.name as bookmaker_name_full
                             FROM bets b
                             LEFT JOIN fixtures f ON b.fixture_id = f.id
                             LEFT JOIN leagues l ON f.league_id = l.id
                             LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id";
                $extras = $db->query($sqlExtra)->fetchAll(\PDO::FETCH_ASSOC, \PDO::FETCH_UNIQUE);

                foreach ($history as &$h) {
                    if (isset($extras[$h['id']])) {
                        $h['country'] = $extras[$h['id']]['country'];
                        $h['bookmaker_name_full'] = $extras[$h['id']]['bookmaker_name_full'];
                    } else {
                        $h['country'] = $h['country'] ?? null;
                        $h['bookmaker_name_full'] = $h['bookmaker_name'] ?? 'Puntata';
                    }
                }
            } catch (\Throwable $e) {
                // Ignoriamo errori di join e puliamo i campi per il frontend
                foreach ($history as &$h) {
                    $h['country'] = $h['country'] ?? null;
                    $h['bookmaker_name_full'] = $h['bookmaker_name'] ?? 'Puntata';
                }
            }

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

        // Prevent duplicates
        if ($this->betModel->isPending($input['fixture_id'])) {
            echo json_encode(['status' => 'already_exists']);
            return;
        }

        // Balance Check
        $summary = $this->betModel->getBalanceSummary(\App\Config\Config::INITIAL_BANKROLL);
        $stake = (float) ($input['stake'] ?? 0);

        // --- BETFAIR INTEGRATION START ---
        $betfairId = null;
        $status = 'pending';
        $note = '';
        $logMsg = "";

        try {
            $bf = new \App\Services\BetfairService();
            $confidence = (int) ($input['confidence'] ?? 0);
            $threshold = \App\Config\Config::BETFAIR_CONFIDENCE_THRESHOLD;
            $logMsg = "Processo scommessa per fixture {$input['fixture_id']}. Confidence: $confidence% (Soglia: $threshold%). ";

            if ($bf->isConfigured() && $confidence >= $threshold) {
                $logMsg .= "Soglia raggiunta, procedo su Betfair. ";
                $matchName = $input['match_name'] ?? '';
                if (!$matchName && isset($input['home_team']) && isset($input['away_team'])) {
                    $matchName = $input['home_team'] . ' v ' . $input['away_team'];
                }

                if ($matchName) {
                    $marketType = 'MATCH_ODDS';
                    if (strpos(strtoupper($input['market'] ?? ''), 'OVER') !== false || strpos(strtoupper($input['market'] ?? ''), 'UNDER') !== false) {
                        $note .= "[WARN] Mercati O/U non supportati. ";
                    } else {
                        $market = $bf->findMarket($matchName, $marketType);
                        if ($market) {
                            $selectionId = $bf->mapAdviceToSelection($input['prediction'] ?? $input['advice'] ?? '', $market['runners']);
                            if ($selectionId) {
                                $price = (float) ($input['odds'] ?? 1.01);
                                if ($price < 1.01) $price = 1.01;

                                // Regole Betfair.it
                                if ($stake < \App\Config\Config::MIN_BETFAIR_STAKE) {
                                    $stake = \App\Config\Config::MIN_BETFAIR_STAKE;
                                    $note .= "[REGOLE] Stake aumentato a 2.00€. ";
                                }
                                $roundedStake = floor($stake * 2) / 2;
                                if ($roundedStake < \App\Config\Config::MIN_BETFAIR_STAKE) $roundedStake = \App\Config\Config::MIN_BETFAIR_STAKE;
                                if ($roundedStake != $stake) {
                                    $stake = $roundedStake;
                                    $note .= "[REGOLE] Stake arrotondato a {$stake}€. ";
                                }

                                $order = $bf->placeBet($market['marketId'], $selectionId, $price, $stake);
                                $result = isset($order['result']) ? $order['result'] : $order;

                                if (isset($result['status']) && $result['status'] === 'SUCCESS') {
                                    $instruction = $result['instructionReports'][0] ?? null;
                                    if ($instruction && $instruction['status'] === 'SUCCESS') {
                                        $betfairId = $instruction['betId'];
                                        $status = 'placed';
                                        $note .= "[BETFAIR] OK! ID: $betfairId. ";
                                    } else {
                                        $note .= "[BETFAIR ERROR] " . ($instruction['errorCode'] ?? 'UNKNOWN') . ". ";
                                    }
                                } else {
                                    $note .= "[BETFAIR API ERROR] " . json_encode($order);
                                }
                            } else {
                                $note .= "[BETFAIR] Selezione non trovata. ";
                            }
                        } else {
                            $note .= "[BETFAIR] Mercato non trovato. ";
                        }
                    }
                }
            } else {
                $logMsg .= "Soglia non raggiunta o non configurato. ";
            }
        } catch (\Throwable $e) {
            $note .= "[BETFAIR EXCEPTION] " . $e->getMessage();
        }

        // Log decision
        error_log("[BET_CONTROLLER] " . $logMsg . " Note: $note");
        // --- BETFAIR INTEGRATION END ---

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

            try {
                $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                        FROM bets b
                        LEFT JOIN fixtures f ON b.fixture_id = f.id
                        LEFT JOIN leagues l ON f.league_id = l.id
                        LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                        ORDER BY b.timestamp DESC LIMIT 1000";
                $allBets = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $sql = "SELECT b.*, l.country_name as country
                        FROM bets b
                        LEFT JOIN fixtures f ON b.fixture_id = f.id
                        LEFT JOIN leagues l ON f.league_id = l.id
                        ORDER BY b.timestamp DESC LIMIT 1000";
                $allBets = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($allBets as &$bet) {
                    $bet['bookmaker_name_full'] = $bet['bookmaker_name'] ?? 'Puntata';
                }
            }

            $balanceSummary = $this->betModel->getBalanceSummary(\App\Config\Config::INITIAL_BANKROLL);
            $statsSummary = [
                'netProfit' => $balanceSummary['realized_profit'],
                'roi' => 0,
                'winCount' => 0,
                'lossCount' => 0,
                'currentPortfolio' => $balanceSummary['current_portfolio'],
                'available_balance' => $balanceSummary['available_balance'],
                'pending_stakes' => $balanceSummary['pending_stakes']
            ];

            $totalStakedSet = 0;
            foreach ($allBets as $bet) {
                if ($bet['status'] === 'won') { $statsSummary['winCount']++; $totalStakedSet += $bet['stake']; }
                elseif ($bet['status'] === 'lost') { $statsSummary['lossCount']++; $totalStakedSet += $bet['stake']; }
            }
            if ($totalStakedSet > 0) $statsSummary['roi'] = ($statsSummary['netProfit'] / $totalStakedSet) * 100;

            $bets = array_filter($allBets, function ($bet) use ($status) {
                if ($status === 'all') return true;
                return ($bet['status'] ?? '') === $status;
            });

            require __DIR__ . '/../Views/partials/tracker.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore caricamento tracker: ' . $e->getMessage() . '</div>';
        }
    }

    public function getRealBalance()
    {
        header('Content-Type: application/json');
        try {
            $bf = new \App\Services\BetfairService();
            if (!$bf->isConfigured()) { echo json_encode(['error' => 'Betfair not configured']); return; }

            $funds = $bf->getFunds();
            $data = isset($funds['result']) ? $funds['result'] : $funds;

            if (isset($data['availableToBetBalance'])) {
                $avail = $data['availableToBetBalance'] ?? 0;
                $exposure = $data['exposure'] ?? 0;
                $wallet = $avail + abs($exposure);
                echo json_encode([
                    'available' => $avail,
                    'exposure' => $exposure,
                    'wallet' => $wallet,
                    'currency' => $data['currencyCode'] ?? 'EUR',
                    'wallet_name' => $data['wallet'] ?? 'Unknown'
                ]);
            } else {
                echo json_encode(['error' => 'API Error', 'details' => $funds]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

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

        $confidence = (int) ($input['confidence'] ?? 0);

        // Verifica disponibilità locale (solo per tracciamento profitto simulato)
        // Nota: Per bet reali (Confidence > 80), Betfair farà il controllo saldo reale.
        // Simuliamo il blocco solo per bet a bassa confidenza (che rimangono virtuali) e se c'è un blocco attivo
        if ($confidence <= 80 && $stake > $summary['available_balance']) {
            // Opzionale: Bloccare o solo avvisare? Proseguiamo per ora
            // echo json_encode(['error' => 'Insufficient balance...']); return;
        }

        // --- BETFAIR INTEGRATION START ---
        $betfairId = null;
        $status = 'pending';
        $note = '';

        try {
            // Instanzia servizio
            $bf = new \App\Services\BetfairService();

            // Cerchiamo l'evento su Betfair usando i nomi squadre (input ha 'match_name' o 'teams')
            // Assumiamo che input['match_name'] sia tipo "Inter v Milan"

            // FILTRO CONFIDENZA: Solo bet con confidence > 80% vanno su Betfair REALE
            $confidence = (int) ($input['confidence'] ?? 0);

            if (($bf->isConfigured() || \App\Config\Config::get('BETFAIR_SESSION_TOKEN')) && $confidence > 80) {
                $matchName = $input['match_name'] ?? '';
                // Se manca match_name, prova a costruirlo
                if (!$matchName && isset($input['home_team']) && isset($input['away_team'])) {
                    $matchName = $input['home_team'] . ' v ' . $input['away_team'];
                }

                if ($matchName) {
                    // 1. Trova Mercato (Es. MATCH_ODDS)
                    // TODO: Gestire altri mercati (Over/Under). Per ora default MATCH_ODDS se input['market'] è "1X2" o simile
                    $marketType = 'MATCH_ODDS'; // Default
                    if (strpos(strtoupper($input['market'] ?? ''), 'OVER') !== false || strpos(strtoupper($input['market'] ?? ''), 'UNDER') !== false) {
                        // TODO: Implementare logica complessa per O/U. Per ora saltiamo se non è 1X2
                        $note .= "[WARN] Mercati O/U non ancora supportati per auto-bet. ";
                    } else {
                        $market = $bf->findMarket($matchName, $marketType);

                        if ($market) {
                            // 2. Trova Selezione (Es. "Inter")
                            $selectionId = $bf->mapAdviceToSelection($input['prediction'] ?? $input['advice'] ?? '', $market['runners']);

                            if ($selectionId) {
                                // 3. Piazza Scommessa
                                // Prezzo e Size dall'input
                                $price = (float) ($input['odds'] ?? 1.01);
                                if ($price < 1.01)
                                    $price = 1.01;

                                $order = $bf->placeBet($market['marketId'], $selectionId, $price, $stake);

                                if (isset($order['result']) && $order['result']['status'] === 'SUCCESS') {
                                    $instruction = $order['result']['instructionReports'][0];
                                    if ($instruction['status'] === 'SUCCESS') {
                                        $betfairId = $instruction['betId'];
                                        $status = 'placed'; // Conferma che è su Betfair
                                        $note .= "[BETFAIR] Ordine piazzato! ID: $betfairId. ";
                                    } else {
                                        $errorCode = $instruction['errorCode'] ?? 'UNKNOWN';
                                        $note .= "[BETFAIR ERROR] $errorCode. ";
                                    }
                                } elseif (isset($order['error'])) {
                                    $note .= "[BETFAIR API ERROR] " . $order['error']['message'] . " (" . ($order['error']['data']['APINGException']['errorCode'] ?? '') . ")";
                                }
                            } else {
                                $note .= "[BETFAIR] Selezione non trovata per '{$input['prediction']}'. ";
                            }
                        } else {
                            $note .= "[BETFAIR] Mercato non trovato per '$matchName'. ";
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $note .= "[BETFAIR EXCEPTION] " . $e->getMessage();
        }
        // --- BETFAIR INTEGRATION END ---

        // Saliamo l'ID Betfair nel DB locale se disponibile
        if ($betfairId) {
            $input['betfair_id'] = $betfairId;
        }
        $input['status'] = $status; // 'placed' se andato a buon fine, 'pending' altrimenti
        $input['notes'] = ($input['notes'] ?? '') . ' ' . $note;

        $id = $this->betModel->create($input);

        echo json_encode(['status' => 'success', 'id' => $id, 'betfair_notes' => $note]);
    }

    /**
     * Serves the tracker partial for HTMX
     */
    public function viewTracker()
    {
        try {
            $status = $_GET['status'] ?? 'all';

            // Reusing logic from getHistory but adapting for PHP view usage
            $db = \App\Services\Database::getInstance()->getConnection();

            // Base query for bets
            try {
                $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                        FROM bets b
                        LEFT JOIN fixtures f ON b.fixture_id = f.id
                        LEFT JOIN leagues l ON f.league_id = l.id
                        LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                        ORDER BY b.timestamp DESC LIMIT 1000";
                $allBets = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // Fallback for missing bookmaker_id
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

            // Calculate summary stats using the new model method
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
                if ($bet['status'] === 'won') {
                    $statsSummary['winCount']++;
                    $totalStakedSet += $bet['stake'];
                } elseif ($bet['status'] === 'lost') {
                    $statsSummary['lossCount']++;
                    $totalStakedSet += $bet['stake'];
                }
            }

            if ($totalStakedSet > 0) {
                $statsSummary['roi'] = ($statsSummary['netProfit'] / $totalStakedSet) * 100;
            }

            // Filter for display
            $bets = array_filter($allBets, function ($bet) use ($status) {
                if ($status === 'all')
                    return true;
                return ($bet['status'] ?? '') === $status;
            });

            // Pass variables to view
            require __DIR__ . '/../Views/partials/tracker.php';

        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore caricamento tracker: ' . $e->getMessage() . '</div>';
        }
    }

    /**
     * API: Get Real Betfair Balance
     * GET /api/betfair/balance
     */
    public function getRealBalance()
    {
        header('Content-Type: application/json');

        try {
            $bf = new \App\Services\BetfairService();
            if (!$bf->isConfigured() && !\App\Config\Config::get('BETFAIR_SESSION_TOKEN')) {
                echo json_encode(['error' => 'Betfair not configured']);
                return;
            }

            $funds = $bf->getFunds();

            if (isset($funds['result'])) {
                $avail = $funds['result']['availableToBetBalance'] ?? 0;
                $exposure = $funds['result']['exposure'] ?? 0;
                // wallet = disponibile + esposizione (circa)
                $wallet = $avail + abs($exposure);

                echo json_encode([
                    'available' => $avail,
                    'exposure' => $exposure,
                    'wallet' => $wallet,
                    'currency' => $funds['result']['currencyCode'] ?? 'EUR'
                ]);
            } else {
                echo json_encode(['error' => 'API Error', 'details' => $funds]);
            }

        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

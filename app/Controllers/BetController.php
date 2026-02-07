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

        $id = $this->betModel->create($input);

        echo json_encode(['status' => 'success', 'id' => $id]);
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
            $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                    FROM bets b
                    LEFT JOIN fixtures f ON b.fixture_id = f.id
                    LEFT JOIN leagues l ON f.league_id = l.id
                    LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                    ORDER BY b.timestamp DESC LIMIT 1000";

            $allBets = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate summary stats on ALL bets (before filtering for display)
            $statsSummary = [
                'netProfit' => 0,
                'roi' => 0,
                'winCount' => 0,
                'lossCount' => 0,
                'currentPortfolio' => 0 // This should ideally come from a user bankroll setting, but we sum up net profit + initial
            ];

            $totalStaked = 0;

            foreach ($allBets as $bet) {
                if ($bet['status'] === 'won') {
                    $profit = $bet['stake'] * ($bet['odds'] - 1);
                    $statsSummary['netProfit'] += $profit;
                    $statsSummary['winCount']++;
                } elseif ($bet['status'] === 'lost') {
                    $statsSummary['netProfit'] -= $bet['stake'];
                    $statsSummary['lossCount']++;
                }
                $totalStaked += $bet['stake'];
            }

            if ($totalStaked > 0) {
                $statsSummary['roi'] = ($statsSummary['netProfit'] / $totalStaked) * 100;
            }
            // Bankroll simulation (starting presumably at 0 or just showing net profit for now)
            $statsSummary['currentPortfolio'] = $statsSummary['netProfit'];

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
}

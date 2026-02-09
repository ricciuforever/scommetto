<?php
// app/Controllers/StandingController.php

namespace App\Controllers;

use App\Services\FootballApiService;

class StandingController
{
    /**
     * Carica la vista delle classifiche
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/standings.php';
        if (file_exists($file)) {
            require $file;
        } else {
            // Fallback
            require __DIR__ . '/../Views/shared/header.php';
            echo '<div class="container mx-auto px-4 py-8"><h1 class="text-3xl text-white font-bold mb-4">Classifiche</h1></div>';
            require __DIR__ . '/../Views/shared/footer.php';
        }
    }

    /**
     * API: Returns standings
     * /api/standings?league=ID&season=YEAR
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;
            $teamId = $_GET['team'] ?? null;

            if ((!$leagueId || !$season) && !$teamId) {
                http_response_code(400);
                echo json_encode(['error' => 'League/Season or Team ID required']);
                return;
            }

            $api = new FootballApiService();
            $result = $api->fetchStandings($leagueId, $season, $teamId);

            $processed = [];
            if (!empty($result['response'])) {
                $leagueData = $result['response'][0]['league'] ?? null;
                if ($leagueData && isset($leagueData['standings'])) {
                    foreach ($leagueData['standings'] as $group) {
                        foreach ($group as $row) {
                            $processed[] = [
                                'rank' => $row['rank'],
                                'team_id' => $row['team']['id'],
                                'team_name' => $row['team']['name'],
                                'team_logo' => $row['team']['logo'],
                                'points' => $row['points'],
                                'goals_diff' => $row['goalsDiff'],
                                'group_name' => $row['group'],
                                'form' => $row['form'],
                                'status' => $row['status'],
                                'description' => $row['description'],
                                'played' => $row['all']['played'],
                                'win' => $row['all']['win'],
                                'draw' => $row['all']['draw'],
                                'lose' => $row['all']['lose'],
                                'goals_for' => $row['all']['goals']['for'],
                                'goals_against' => $row['all']['goals']['against']
                            ];
                        }
                    }
                }
            }

            echo json_encode(['response' => $processed]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

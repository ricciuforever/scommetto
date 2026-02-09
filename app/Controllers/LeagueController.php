<?php
// app/Controllers/LeagueController.php

namespace App\Controllers;

use App\Services\FootballApiService;
use App\Models\LeagueStats;

class LeagueController
{
    /**
     * Carica la vista delle leghe
     */
    public function index()
    {
        // Placeholder for future implementation
        // For now, return a simple JSON or render a placeholder view
        // Ideally render Views/leagues.php if exists
        $view = __DIR__ . '/../Views/leagues.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo "<h1>Leghe</h1><p>Lista delle competizioni disponibili.</p>";
        }
    }

    /**
     * API: Returns list of leagues
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $api = new FootballApiService();
            $params = $_GET;
            unset($params['url']); // Remove internal routing param if present

            $result = $api->fetchLeagues($params);

            echo json_encode(['response' => $result['response'] ?? []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Returns top stats (scorers, assists, cards)
     * Queries: league, season, type
     */
    public function topStats()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;
            $type = $_GET['type'] ?? 'scorers'; // scorers, assists, yellow, red

            if (!$leagueId || !$season) {
                http_response_code(400);
                echo json_encode(['error' => 'League ID and Season are required']);
                return;
            }

            // Normalize type
            $validTypes = ['scorers', 'assists', 'yellowcards', 'redcards'];
            $apiType = $type;
            if ($type === 'yellow')
                $apiType = 'yellowcards';
            if ($type === 'red')
                $apiType = 'redcards';

            $statsModel = new LeagueStats();
            $cached = $statsModel->get($leagueId, $season, $apiType);

            // Return cached if valid (24h)
            if ($cached && !$statsModel->isStale($cached['last_updated'], 24)) {
                echo json_encode(['response' => $cached['data']]);
                return;
            }

            // Fetch from API
            $api = new FootballApiService();
            $data = [];

            switch ($apiType) {
                case 'scorers':
                    $data = $api->fetchTopScorers($leagueId, $season);
                    break;
                case 'assists':
                    $data = $api->fetchTopAssists($leagueId, $season);
                    break;
                case 'yellowcards':
                    $data = $api->fetchTopYellowCards($leagueId, $season);
                    break;
                case 'redcards':
                    $data = $api->fetchTopRedCards($leagueId, $season);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid type']);
                    return;
            }

            if (isset($data['response'])) {
                $statsModel->save($leagueId, $season, $apiType, $data['response']);
                echo json_encode(['response' => $data['response']]);
            } else {
                // Return API error or empty
                echo json_encode($data);
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

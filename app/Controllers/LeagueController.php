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
    /**
     * API: Returns list of leagues
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $model = new \App\Models\League();
            $leagues = $model->getAll();

            // Refresh if empty or requested (or if stale logic is needed)
            if (empty($leagues) || isset($_GET['refresh'])) {
                $api = new FootballApiService();
                $params = $_GET;
                unset($params['url'], $params['refresh']); // Clean params

                // If filters are present (other than refresh), we might want to respect them for the API call 
                // BUT League::save consumes the standard API response structure.
                // Ideally we fetch *all* relevant leagues or just serve from DB.
                // For now, if DB is empty, we force a fetch.

                $result = $api->fetchLeagues($params);

                if (!empty($result['response'])) {
                    foreach ($result['response'] as $item) {
                        $model->save($item);
                    }
                    // Refetch from DB to get the flat structure
                    $leagues = $model->getAll(); // Or use find($params) if we want to filter
                }
            }

            // Apply search/filtering in memory or via DB find() if needed.
            // But since the view does client-side filtering, returning all or filtered by DB is fine.
            // The view expects: id, name, type, country_name, logo, coverage_json

            // If filters are passed (like country, season), use $model->find($filters)
            if (!empty($_GET['country']) || !empty($_GET['search']) || !empty($_GET['type'])) {
                $leagues = $model->find($_GET);
            }

            echo json_encode(['response' => $leagues]);

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

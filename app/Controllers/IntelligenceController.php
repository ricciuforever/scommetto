<?php
// app/Controllers/IntelligenceController.php

namespace App\Controllers;

use App\Services\FootballApiService;

class IntelligenceController
{
    /**
     * Renders the Intelligence Dashboard view
     */
    public function index()
    {
        $pageTitle = 'Scommetto.AI - Live Intelligence';
        $view = __DIR__ . '/../Views/intelligence.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo "<h1>Intelligence Dashboard</h1><p>View not found.</p>";
        }
    }

    /**
     * API: Returns live odds/events with optional filters
     * Endpoint: /api/intelligence/live
     */
    public function live()
    {
        header('Content-Type: application/json');
        try {
            $api = new FootballApiService();
            $params = [];

            // Apply filters from GET request
            if (!empty($_GET['bookmaker'])) {
                $params['bookmaker'] = $_GET['bookmaker'];
            }
            if (!empty($_GET['league'])) {
                $params['league'] = $_GET['league'];
            }
            if (!empty($_GET['bet'])) {
                $params['bet'] = $_GET['bet'];
            }

            // Fetch live odds (which include fixture info)
            // Note: API-Football /odds/live returns a list of fixtures with odds
            $data = $api->fetchLiveOdds($params);

            // Fetch Bookmakers list for the filter dropdown if needed, 
            // but the frontend can fetch that separately from /api/odds/bookmakers

            echo json_encode(['response' => $data['response'] ?? []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

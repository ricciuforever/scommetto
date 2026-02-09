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
            if (!empty($_GET['league'])) {
                $params['league'] = $_GET['league'];
            }

            // Fetch live matches with complete data
            $data = $api->fetchLiveMatches($params);

            // The response structure from API-Football /fixtures?live=all:
            // response[]: { fixture: {...}, league: {...}, teams: {...}, goals: {...}, score: {...} }

            echo json_encode(['response' => $data['response'] ?? []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

<?php
// app/Controllers/FixtureStatsController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixtureStatistics;
use App\Services\FootballApiService;

class FixtureStatsController
{
    /**
     * Carica la vista delle statistiche match
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_stats.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Statistiche Match non trovata</h1>";
        }
    }

    /**
     * Ritorna le statistiche JSON per un fixture (sincronizza se necessario)
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $fixtureId = $_GET['fixture'] ?? null;

            if (!$fixtureId) {
                echo json_encode(['response' => [], 'message' => 'Fixture ID richiesto.']);
                return;
            }

            $model = new FixtureStatistics();
            $fixtureModel = new Fixture();

            // Recuperiamo lo stato del match per decidere il refresh
            $fixture = $fixtureModel->getById($fixtureId);
            $status = $fixture['status_short'] ?? 'NS';

            if ($model->needsRefresh($fixtureId, $status)) {
                $this->sync($fixtureId);
            }

            $stats = $model->getByFixture($fixtureId);
            echo json_encode(['response' => $stats]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le statistiche dall'API
     */
    private function sync($fixtureId)
    {
        $api = new FootballApiService();
        $model = new FixtureStatistics();

        $data = $api->fetchFixtureStatistics($fixtureId);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                if (isset($item['team']['id'])) {
                    $model->save($fixtureId, $item['team']['id'], $item['statistics']);
                }
            }
        }
    }
}

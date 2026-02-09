<?php
// app/Controllers/FixturePlayerStatsController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixturePlayerStatistics;
use App\Services\FootballApiService;

class FixturePlayerStatsController
{
    /**
     * Carica la vista delle statistiche giocatori per un match
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_player_stats.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Statistiche Giocatori non trovata</h1>";
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

            $model = new FixturePlayerStatistics();
            $fixtureModel = new Fixture();

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
        $model = new FixturePlayerStatistics();

        $data = $api->fetchFixturePlayerStatistics($fixtureId);

        if (isset($data['response']) && is_array($data['response'])) {
            // Eliminiamo le vecchie statistiche per evitare duplicati
            $model->deleteByFixture($fixtureId);

            foreach ($data['response'] as $teamData) {
                $teamId = $teamData['team']['id'];
                foreach ($teamData['players'] as $playerData) {
                    $playerId = $playerData['player']['id'];
                    $stats = $playerData['statistics'][0] ?? []; // Prendiamo la prima (e unica per fixture) statistica
                    $model->save($fixtureId, $teamId, $playerId, $stats);
                }
            }
        }
    }
}

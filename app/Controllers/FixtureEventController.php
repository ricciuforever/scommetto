<?php
// app/Controllers/FixtureEventController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixtureEvent;
use App\Services\FootballApiService;

class FixtureEventController
{
    /**
     * Carica la vista degli eventi match
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_events.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Eventi Match non trovata</h1>";
        }
    }

    /**
     * Ritorna gli eventi JSON per un fixture (sincronizza se necessario)
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

            $model = new FixtureEvent();
            $fixtureModel = new Fixture();

            $fixture = $fixtureModel->getById($fixtureId);
            $status = $fixture['status_short'] ?? 'NS';

            if ($model->needsRefresh($fixtureId, $status)) {
                $this->sync($fixtureId);
            }

            $events = $model->getByFixture($fixtureId);
            echo json_encode(['response' => $events]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza gli eventi dall'API
     */
    private function sync($fixtureId)
    {
        $api = new FootballApiService();
        $model = new FixtureEvent();

        $data = $api->fetchFixtureEvents($fixtureId);

        if (isset($data['response']) && is_array($data['response'])) {
            // Eliminiamo i vecchi eventi per evitare duplicati
            $model->deleteByFixture($fixtureId);

            foreach ($data['response'] as $item) {
                $model->save($fixtureId, $item);
            }
        }
    }
}

<?php
// app/Controllers/FixtureInjuryController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixtureInjury;
use App\Services\FootballApiService;

class FixtureInjuryController
{
    /**
     * Carica la vista degli infortuni e squalifiche
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_injuries.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Infortuni non trovata</h1>";
        }
    }

    /**
     * Ritorna gli infortuni JSON per un fixture (sincronizza se necessario)
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

            $model = new FixtureInjury();
            $fixtureModel = new Fixture();

            $fixture = $fixtureModel->getById($fixtureId);
            $status = $fixture['status_short'] ?? 'NS';

            if ($model->needsRefresh($fixtureId, $status)) {
                $this->sync($fixtureId);
            }

            $injuries = $model->getByFixture($fixtureId);
            echo json_encode(['response' => $injuries]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza gli infortuni dall'API
     */
    private function sync($fixtureId)
    {
        $api = new FootballApiService();
        $model = new FixtureInjury();

        $data = $api->fetchFixtureInjuries($fixtureId);

        if (isset($data['response']) && is_array($data['response'])) {
            // Eliminiamo i vecchi record per evitare duplicati
            $model->deleteByFixture($fixtureId);

            foreach ($data['response'] as $item) {
                $model->save($fixtureId, $item);
            }
        }
    }
}

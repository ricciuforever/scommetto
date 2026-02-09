<?php
// app/Controllers/FixturePredictionController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixturePrediction;
use App\Services\FootballApiService;

class FixturePredictionController
{
    /**
     * Carica la vista dei pronostici per un match
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_predictions.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Pronostici non trovata</h1>";
        }
    }

    /**
     * Ritorna i pronostici JSON per un fixture (sincronizza se necessario)
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $fixtureId = $_GET['fixture'] ?? null;

            if (!$fixtureId) {
                echo json_encode(['response' => null, 'message' => 'Fixture ID richiesto.']);
                return;
            }

            $model = new FixturePrediction();
            $fixtureModel = new Fixture();

            $fixture = $fixtureModel->getById($fixtureId);
            $status = $fixture['status_short'] ?? 'NS';

            if ($model->needsRefresh($fixtureId, $status)) {
                $this->sync($fixtureId);
            }

            $prediction = $model->getByFixture($fixtureId);
            echo json_encode(['response' => $prediction]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza i pronostici dall'API
     */
    private function sync($fixtureId)
    {
        $api = new FootballApiService();
        $model = new FixturePrediction();

        $data = $api->fetchPredictions($fixtureId);

        if (isset($data['response']) && is_array($data['response']) && count($data['response']) > 0) {
            $resp = $data['response'][0];
            $model->save($fixtureId, $resp['predictions'], $resp['comparison']);
        }
    }
}

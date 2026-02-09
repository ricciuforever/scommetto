<?php
// app/Controllers/FixtureLineupController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\FixtureLineup;
use App\Services\FootballApiService;

class FixtureLineupController
{
    /**
     * Carica la vista delle formazioni
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/fixture_lineups.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Formazioni non trovata</h1>";
        }
    }

    /**
     * Ritorna le formazioni JSON per un fixture (sincronizza se necessario)
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

            $model = new FixtureLineup();
            $fixtureModel = new Fixture();

            $fixture = $fixtureModel->getById($fixtureId);
            $status = $fixture['status_short'] ?? 'NS';

            if ($model->needsRefresh($fixtureId, $status)) {
                $this->sync($fixtureId);
            }

            $lineups = $model->getByFixture($fixtureId);

            // Format data to match API structure
            $formatted = [];
            foreach ($lineups as $lineup) {
                $formatted[] = [
                    'team' => [
                        'id' => $lineup['team_id'],
                        'name' => $lineup['team_name'],
                        'logo' => $lineup['team_logo']
                    ],
                    'formation' => $lineup['formation'],
                    'startXI' => $lineup['start_xi_json'] ?? [],
                    'substitutes' => $lineup['substitutes_json'] ?? []
                ];
            }

            echo json_encode(['response' => $formatted]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le formazioni dall'API
     */
    private function sync($fixtureId)
    {
        $api = new FootballApiService();
        $model = new FixtureLineup();

        $data = $api->fetchFixtureLineups($fixtureId);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                if (isset($item['team']['id'])) {
                    $model->save($fixtureId, $item['team']['id'], $item);
                }
            }
        }
    }
}

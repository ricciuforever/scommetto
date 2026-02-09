<?php
// app/Controllers/H2HController.php

namespace App\Controllers;

use App\Models\H2H;
use App\Models\Team;
use App\Services\FootballApiService;

class H2HController
{
    /**
     * Carica la vista dei confronti testa a testa
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/h2h.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Head to Head non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON dei confronti testa a testa (sincronizza se necessario)
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $t1 = $_GET['t1'] ?? null;
            $t2 = $_GET['t2'] ?? null;

            if (!$t1 || !$t2) {
                echo json_encode(['response' => [], 'message' => 'Team 1 e Team 2 richiesti (es ?t1=33&t2=34).']);
                return;
            }

            $model = new H2H();

            // Sincronizzazione on-demand (scadenza 7 giorni per H2H storici)
            if ($model->needsRefresh($t1, $t2, 168)) {
                $this->sync($t1, $t2);
            }

            $record = $model->get($t1, $t2);
            echo json_encode(['response' => $record ? $record['h2h_json'] : []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza i dati H2H dall'API
     */
    private function sync($t1, $t2)
    {
        $api = new FootballApiService();
        $model = new H2H();
        $teamModel = new Team();

        // L'API richiede il parametro in formato "id1-id2"
        $h2hParam = $t1 . '-' . $t2;
        $data = $api->fetchH2H($h2hParam);

        if (isset($data['response']) && is_array($data['response'])) {
            $model->save($t1, $t2, $data['response']);

            // Opzionale: salviamo i team se non esistono (spesso presenti nel blocco teams)
            if (!empty($data['response'])) {
                $first = $data['response'][0];
                $teamModel->save($first['teams']['home']);
                $teamModel->save($first['teams']['away']);
            }
        }
    }
}

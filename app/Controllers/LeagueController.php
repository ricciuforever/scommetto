<?php
// app/Controllers/LeagueController.php

namespace App\Controllers;

use App\Models\League;
use App\Services\FootballApiService;

class LeagueController
{
    /**
     * Carica la vista delle competizioni (React)
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/leagues.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Leagues non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON delle competizioni con aggiornamento on-demand (1h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $model = new League();

            // Verifica se i dati nel DB sono scaduti (1 ora per le competizioni)
            if ($model->needsRefresh(1)) {
                $this->sync();
            }

            $leagues = $model->getAll();
            echo json_encode(['response' => $leagues]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le competizioni dall'API al Database
     */
    private function sync()
    {
        $api = new FootballApiService();
        $model = new League();

        $data = $api->fetchLeagues();

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                $model->save($item);
            }
        }
    }
}

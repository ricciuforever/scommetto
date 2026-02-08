<?php
// app/Controllers/SeasonController.php

namespace App\Controllers;

use App\Models\Season;
use App\Services\FootballApiService;

class SeasonController
{
    /**
     * Carica la vista delle stagioni
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/seasons.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Stagioni non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON delle stagioni con aggiornamento on-demand (24h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $model = new Season();

            if ($model->needsRefresh(24)) {
                $this->sync();
            }

            $seasons = $model->getAll();
            echo json_encode(['response' => $seasons]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le stagioni dall'API al Database
     */
    private function sync()
    {
        $api = new FootballApiService();
        $model = new Season();

        $data = $api->fetchSeasons();

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $year) {
                $model->save($year);
            }
        }
    }
}

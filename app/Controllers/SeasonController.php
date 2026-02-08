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
            // Filtra solo anni sensati (es. >= 2010 e <= anno attuale + 2)
            $thisYear = (int)date('Y');
            $seasons = array_filter($seasons, function($y) use ($thisYear) {
                return $y >= 2010 && $y <= ($thisYear + 1);
            });
            $seasons = array_values($seasons);

            $current = \App\Config\Config::getCurrentSeason();
            echo json_encode([
                'response' => $seasons,
                'current' => $current
            ]);
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

<?php
// app/Controllers/RoundController.php

namespace App\Controllers;

use App\Models\Round;
use App\Services\FootballApiService;

class RoundController
{
    /**
     * Ritorna i round per una lega/stagione con sync on-demand
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;

            if (!$leagueId || !$season) {
                echo json_encode(['response' => [], 'message' => 'Lega e Stagione richieste.']);
                return;
            }

            $model = new Round();

            // Sincronizzazione on-demand (scadenza 24 ore come da suggerimento API)
            if ($model->needsRefresh($leagueId, $season, 24)) {
                $this->sync($leagueId, $season);
            }

            $rounds = $model->getByLeagueSeason($leagueId, $season);
            echo json_encode(['response' => $rounds]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza i round dall'API
     */
    private function sync($leagueId, $season)
    {
        $api = new FootballApiService();
        $model = new Round();

        $data = $api->fetchLeaguesRounds($leagueId, $season);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $roundName) {
                $model->save($leagueId, $season, $roundName);
            }
        }
    }
}

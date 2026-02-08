<?php
// app/Controllers/TeamController.php

namespace App\Controllers;

use App\Models\Team;
use App\Services\FootballApiService;

class TeamController
{
    /**
     * Carica la vista delle squadre
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/teams.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Squadre non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON delle squadre per lega/stagione con aggiornamento on-demand (24h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;

            if (!$leagueId || !$season) {
                echo json_encode(['response' => [], 'message' => 'Lega e Stagione sono richieste.']);
                return;
            }

            $model = new Team();

            // Verifica se i dati per questa lega/stagione sono scaduti (24 ore)
            if ($model->needsLeagueRefresh($leagueId, $season, 24)) {
                $this->sync($leagueId, $season);
            }

            $teams = $model->getByLeagueAndSeason($leagueId, $season);
            echo json_encode(['response' => $teams]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le squadre dall'API al Database per una specifica lega/stagione
     */
    private function sync($leagueId, $season)
    {
        $api = new FootballApiService();
        $model = new Team();

        $data = $api->fetchTeams(['league' => $leagueId, 'season' => $season]);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                // Il modello Team::save gestisce internamente anche il Venue
                $model->save($item);
                // Collega la squadra alla lega/stagione
                $model->linkToLeague($item['team']['id'], $leagueId, $season);
            }
        }
    }
}

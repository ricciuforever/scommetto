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

            $model = new Team();

            // Se abbiamo league e season, proviamo a sincronizzare se necessario
            if ($leagueId && $season) {
                if ($model->needsLeagueRefresh($leagueId, $season, 24)) {
                    $this->sync($leagueId, $season);
                }
            }

            $filters = [
                'league' => $leagueId,
                'season' => $season,
                'id' => $_GET['id'] ?? null,
                'name' => $_GET['name'] ?? null,
                'country' => $_GET['country'] ?? null,
                'code' => $_GET['code'] ?? null,
                'venue' => $_GET['venue'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];

            if (array_filter($filters)) {
                $teams = $model->find($filters);
            } else {
                // Se non ci sono filtri, non restituiamo tutto (troppo pesante)
                echo json_encode(['response' => [], 'message' => 'Filtri richiesti per le squadre.']);
                return;
            }

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
            if (!empty($data['response'])) {
                foreach ($data['response'] as $item) {
                    // Il modello Team::save gestisce internamente anche il Venue
                    $model->save($item);
                    // Collega la squadra alla lega/stagione
                    $model->linkToLeague($item['team']['id'], $leagueId, $season);
                }
            }
            // Segniamo che il sync è avvenuto per questa lega/stagione (anche se vuoto)
            // per evitare di riprovare ad ogni richiesta
            $model->touchLeagueSeason($leagueId, $season);
        } elseif (isset($data['errors']) && !empty($data['errors'])) {
            // In caso di errore esplicito dall'API (es: stagione non valida),
            // segniamo comunque il sync come tentato per evitare loop
            $model->touchLeagueSeason($leagueId, $season);
        }
    }

    /**
     * Ritorna le stagioni disponibili per una squadra (on-demand 24h)
     */
    public function seasons()
    {
        header('Content-Type: application/json');
        try {
            $teamId = $_GET['team'] ?? null;
            if (!$teamId) {
                echo json_encode(['error' => 'Team ID richiesto']);
                return;
            }

            $model = new Team();
            if ($model->needsTeamSeasonsRefresh($teamId, 24)) {
                $this->syncSeasons($teamId);
            }

            $seasons = $model->getTeamSeasons($teamId);
            echo json_encode(['response' => $seasons]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le stagioni di una squadra dall'API
     */
    private function syncSeasons($teamId)
    {
        $api = new FootballApiService();
        $model = new Team();
        $data = $api->fetchTeamSeasons($teamId);

        if (isset($data['response']) && is_array($data['response'])) {
            $model->saveTeamSeasons($teamId, $data['response']);
        }
    }
}

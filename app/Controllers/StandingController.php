<?php
// app/Controllers/StandingController.php

namespace App\Controllers;

use App\Models\Standing;
use App\Models\Team;
use App\Services\FootballApiService;

class StandingController
{
    /**
     * Carica la vista della classifica
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/standings.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Classifica non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON della classifica con aggiornamento on-demand (1h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $teamId = $_GET['team'] ?? null;
            $season = $_GET['season'] ?? null;

            if (!$season || (!$leagueId && !$teamId)) {
                echo json_encode(['error' => 'Stagione e almeno uno tra Lega o Squadra sono richiesti.']);
                return;
            }

            $model = new Standing();

            // Se abbiamo la lega, proviamo il refresh on-demand
            if ($leagueId && $season) {
                if ($model->needsRefresh($leagueId, $season, 1)) {
                    $this->sync($leagueId, $season);
                }
            }

            $filters = [
                'league' => $leagueId,
                'team' => $teamId,
                'season' => $season
            ];

            $standings = $model->find($filters);

            // Se cerchiamo per team e non abbiamo risultati, proviamo a sincronizzare
            // ma l'API richiede comunque la lega per le classifiche standard.
            // Se l'API supportasse ?team={id}&season={year} direttamente, potremmo farlo.
            // In base agli use case dell'utente: get("https://v3.football.api-sports.io/standings?team=33&season=2019");
            // Quindi l'API lo supporta. Aggiungo supporto al sync per team.

            if (empty($standings) && $teamId && $season) {
                 $this->sync(null, $season, $teamId);
                 $standings = $model->find($filters);
            }

            echo json_encode(['response' => $standings]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza la classifica dall'API al Database
     */
    private function sync($leagueId, $season, $teamId = null)
    {
        $api = new FootballApiService();
        $model = new Standing();
        $teamModel = new Team();

        $data = $api->fetchStandings($leagueId, $season, $teamId);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $standingData) {
                $actualLeagueId = $standingData['league']['id'] ?? $leagueId;
                if (isset($standingData['league']['standings']) && is_array($standingData['league']['standings'])) {
                    foreach ($standingData['league']['standings'] as $group) {
                        foreach ($group as $item) {
                            // Assicuriamoci che la squadra esista nel DB
                            if (!$teamModel->getById($item['team']['id'])) {
                                // Passiamo il wrapper 'team' per consistenza con TeamController::sync
                                $teamModel->save(['team' => $item['team']]);
                            }
                            // Salviamo la riga della classifica
                            $model->save($actualLeagueId, $season, $item);
                        }
                    }
                }
                // Segniamo il sync come avvenuto per questa lega
                if ($actualLeagueId) {
                    $this->touchSync($actualLeagueId, $season);
                }
            }
        }
    }

    /**
     * Segna la sincronizzazione come avvenuta aggiornando il timestamp
     */
    private function touchSync($leagueId, $season)
    {
        $db = \App\Services\Database::getInstance()->getConnection();
        $sql = "INSERT INTO league_seasons (league_id, year, last_standings_sync)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE last_standings_sync = CURRENT_TIMESTAMP";
        $db->prepare($sql)->execute([$leagueId, $season]);
    }
}

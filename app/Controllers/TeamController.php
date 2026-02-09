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
                    // Assicuriamoci che i dati minimi siano presenti
                    if (!isset($item['team']['id']))
                        continue;

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

    /**
     * Ritorna la rosa della squadra o le squadre di un giocatore
     */
    public function squads()
    {
        header('Content-Type: application/json');
        try {
            $teamId = $_GET['team'] ?? null;
            $playerId = $_GET['player'] ?? null;

            if (!$teamId && !$playerId) {
                echo json_encode(['error' => 'Richiesto parametro team o player']);
                return;
            }

            $playerModel = new \App\Models\Player();
            $teamModel = new Team();

            if ($teamId) {
                // Sincronizza rosa team se necessario
                if ($this->needsSquadRefresh($teamId)) {
                    $this->syncSquad($teamId);
                }

                $squad = $playerModel->getByTeam($teamId);
                $team = $teamModel->getById($teamId);

                echo json_encode([
                    'response' => [
                        [
                            'team' => $team,
                            'players' => $squad
                        ]
                    ]
                ]);

            } elseif ($playerId) {
                // Sincronizza squadre giocatore se non presenti
                $teams = $playerModel->getTeams($playerId);
                if (empty($teams)) {
                    $this->syncPlayerSquads($playerId);
                    $teams = $playerModel->getTeams($playerId);
                }

                echo json_encode(['response' => $teams]);
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function needsSquadRefresh($teamId)
    {
        $db = \App\Services\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT MAX(last_updated) FROM squads WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $last = $stmt->fetchColumn();
        return !$last || (time() - strtotime($last)) > (3 * 86400); // 3 giorni
    }

    private function syncSquad($teamId)
    {
        $api = new FootballApiService();
        $playerModel = new \App\Models\Player();
        $data = $api->fetchSquad($teamId);

        if (isset($data['response'][0]['players'])) {
            foreach ($data['response'][0]['players'] as $p) {
                $playerData = [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'age' => $p['age'] ?? null,
                    'photo' => $p['photo'] ?? null
                ];
                $playerModel->save($playerData);

                $squadInfo = [
                    'position' => $p['position'] ?? null,
                    'number' => $p['number'] ?? null
                ];
                $playerModel->linkToSquad($teamId, $playerData, $squadInfo);
            }
        }
    }

    private function syncPlayerSquads($playerId)
    {
        $api = new FootballApiService();
        $teamModel = new Team();
        $playerModel = new \App\Models\Player();
        $data = $api->fetchSquad(null, $playerId);

        if (isset($data['response'])) {
            foreach ($data['response'] as $item) {
                $teamId = $item['team']['id'];
                $teamModel->save($item['team']);

                if (isset($item['players'])) {
                    foreach ($item['players'] as $p) {
                        if ($p['id'] == $playerId) {
                            $playerData = [
                                'id' => $p['id'],
                                'name' => $p['name'],
                                'age' => $p['age'] ?? null,
                                'photo' => $p['photo'] ?? null
                            ];
                            $playerModel->save($playerData);

                            $squadInfo = [
                                'position' => $p['position'] ?? null,
                                'number' => $p['number'] ?? null
                            ];
                            $playerModel->linkToSquad($teamId, $playerData, $squadInfo);
                        }
                    }
                }
            }
        }
    }
}

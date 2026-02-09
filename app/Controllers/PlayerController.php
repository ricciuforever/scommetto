<?php
// app/Controllers/PlayerController.php

namespace App\Controllers;

use App\Models\Player;
use App\Services\FootballApiService;

class PlayerController
{
    /**
     * Carica la vista dei profili giocatori
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/players.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Giocatori non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON dei giocatori (ricerca o profilo singolo)
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            $search = $_GET['search'] ?? null;
            $page = $_GET['page'] ?? 1;

            $model = new Player();
            $api = new FootballApiService();

            if ($playerId) {
                // Cerchiamo nel DB prima
                $player = $model->getById($playerId);
                if (!$player) {
                    $data = $api->fetchPlayerProfiles(['player' => $playerId]);
                    if (!empty($data['response'])) {
                        $model->save($data['response'][0]['player']);
                        $player = $model->getById($playerId);
                    }
                }
                echo json_encode(['response' => $player]);
                return;
            }

            if ($search) {
                $data = $api->fetchPlayerProfiles(['search' => $search]);
                echo json_encode($data);
                return;
            }

            // Default: profili generali con paginazione (solo API per ora, non salviamo tutto il mondo nel DB)
            $data = $api->fetchPlayerProfiles(['page' => $page]);
            echo json_encode($data);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    /**
     * Ritorna la lista delle squadre in cui il giocatore ha militato (Carriera)
     */
    public function teams()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            if (!$playerId) {
                echo json_encode(['error' => 'Player ID richiesto']);
                return;
            }

            $model = new Player();
            $career = $model->getCareer($playerId);

            // Se non abbiamo dati, sincronizziamo
            // Oppure possiamo verificare se è necessario refresh (implementeremo logica refresh in futuro se serve)
            if (empty($career)) {
                $this->syncTeams($playerId);
                $career = $model->getCareer($playerId);
            }

            // Decodifichiamo i JSON per il frontend
            foreach ($career as &$item) {
                $item['seasons'] = json_decode($item['seasons_json']);
                unset($item['seasons_json']);
            }

            echo json_encode(['response' => $career]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }


    /**
     * Ritorna i trasferimenti del giocatore
     */
    public function transfers()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            if (!$playerId) {
                echo json_encode(['error' => 'Player ID richiesto']);
                return;
            }

            $model = new Player();
            $data = $model->getTransfers($playerId);

            // Should refresh if old? User said recommended 1 call per day.
            // Check staleness.
            $isStale = true;
            if ($data) {
                // simple check: if last_updated is < 24h ago
                $last = strtotime($data['last_updated']);
                if (time() - $last < 86400) {
                    $isStale = false;
                }
            }

            if ($isStale || !$data) {
                $api = new FootballApiService();
                $apiResult = $api->fetchTransfers(['player' => $playerId]);

                if (!empty($apiResult['response'])) {
                    // response[0] contains { player, update, transfers }
                    $info = $apiResult['response'][0];
                    $updateDate = $info['update'];
                    $transfers = $info['transfers'];

                    $model->saveTransfers($playerId, $updateDate, $transfers);
                    $data = $model->getTransfers($playerId);
                }
            }

            echo json_encode(['response' => $data ? $data['transfers'] : []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ritorna i trofei del giocatore
     */
    public function trophies()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            if (!$playerId) {
                echo json_encode(['error' => 'Player ID richiesto']);
                return;
            }

            $model = new Player();
            $data = $model->getTrophies($playerId);

            // Check staleness (24h default for user-specific data)
            $isStale = true;
            if ($data) {
                $last = strtotime($data['last_updated']);
                if (time() - $last < 86400) {
                    $isStale = false;
                }
            }

            if ($isStale || !$data) {
                $api = new FootballApiService();
                $apiResult = $api->fetchTrophies(['player' => $playerId]);

                if (!empty($apiResult['response'])) {
                    $model->saveTrophies($playerId, $apiResult['response']);
                    $data = $model->getTrophies($playerId);
                }
            }

            echo json_encode(['response' => $data ? $data['trophies'] : []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ritorna gli infortuni/assenza del giocatore
     */
    public function sidelined()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            if (!$playerId) {
                echo json_encode(['error' => 'Player ID richiesto']);
                return;
            }

            $model = new Player();
            $data = $model->getSidelined($playerId);

            // Check staleness
            $isStale = true;
            if ($data) {
                $last = strtotime($data['last_updated']);
                if (time() - $last < 86400) {
                    $isStale = false;
                }
            }

            if ($isStale || !$data) {
                $api = new FootballApiService();
                $apiResult = $api->fetchSidelined(['player' => $playerId]);

                if (!empty($apiResult['response'])) {
                    $model->saveSidelined($playerId, $apiResult['response']);
                    $data = $model->getSidelined($playerId);
                }
            }

            echo json_encode(['response' => $data ? $data['sidelined'] : []]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function syncTeams($playerId)
    {
        $api = new FootballApiService();
        $model = new Player();
        $teamModel = new \App\Models\Team(); // Assicurarsi che Team model esista e sia importato

        $data = $api->fetchPlayerTeams($playerId);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                // Salviamo le info base della squadra se non le abbiamo
                $teamModel->save($item['team']);
            }
            // Salviamo la carriera
            $model->saveCareer($playerId, $data['response']);
        }
    }
}

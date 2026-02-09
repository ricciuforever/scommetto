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

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
}

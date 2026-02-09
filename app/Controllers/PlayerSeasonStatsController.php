<?php
// app/Controllers/PlayerSeasonStatsController.php

namespace App\Controllers;

use App\Models\Player;
use App\Models\PlayerStatistics;
use App\Services\FootballApiService;

class PlayerSeasonStatsController
{
    /**
     * Ritorna le statistiche stagionali JSON per un giocatore
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $playerId = $_GET['player'] ?? null;
            $season = $_GET['season'] ?? date('Y');

            if (!$playerId) {
                echo json_encode(['response' => [], 'message' => 'Player ID richiesto.']);
                return;
            }

            $model = new PlayerStatistics();
            $playerModel = new Player();
            $api = new FootballApiService();

            // Verifichiamo se abbiamo bisogno di sincronizzare
            // Per semplicità usiamo un controllo basato sull'ultimo aggiornamento del giocatore o una logica specifica
            // Qui sincronizziamo se non abbiamo dati o se sono vecchi di 7 giorni (le stats stagionali cambiano meno spesso)
            $stats = $model->getByPlayer($playerId, $season);

            if (empty($stats) || $this->shouldRefresh($stats)) {
                $this->sync($playerId, $season);
                $stats = $model->getByPlayer($playerId, $season);
            }

            // Decodifichiamo i JSON per il frontend
            foreach ($stats as &$s) {
                if (is_string($s['stats_json'])) {
                    $s['stats_json'] = json_decode($s['stats_json'], true);
                }
            }

            echo json_encode(['response' => $stats]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function shouldRefresh($stats)
    {
        if (empty($stats))
            return true;
        $lastUpdated = strtotime($stats[0]['last_updated']);
        return (time() - $lastUpdated) > (7 * 86400); // 7 giorni
    }

    private function sync($playerId, $season)
    {
        $api = new FootballApiService();
        $model = new PlayerStatistics();
        $playerModel = new Player();

        $data = $api->fetchPlayers(['id' => $playerId, 'season' => $season]);

        if (isset($data['response']) && is_array($data['response']) && count($data['response']) > 0) {
            foreach ($data['response'] as $item) {
                // Aggiorniamo anche il profilo del giocatore già che ci siamo
                $playerModel->save($item['player']);

                // Salviamo le statistiche per ogni team/league trovata (può aver giocato in più team)
                foreach ($item['statistics'] as $stat) {
                    $model->save(
                        $item['player']['id'],
                        $stat['team']['id'],
                        $stat['league']['id'],
                        $season,
                        $stat
                    );
                }
            }
        }
    }
}

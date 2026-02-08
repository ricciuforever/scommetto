<?php
// app/Controllers/TeamStatsController.php

namespace App\Controllers;

use App\Models\TeamStats;
use App\Models\Team;
use App\Models\League;
use App\Services\FootballApiService;

class TeamStatsController
{
    /**
     * Carica la vista delle statistiche della squadra
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/team_stats.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Statistiche Squadra non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON delle statistiche con aggiornamento on-demand (24h)
     */
    public function show()
    {
        header('Content-Type: application/json');
        try {
            $teamId = $_GET['team'] ?? null;
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;
            $date = $_GET['date'] ?? null;

            if (!$teamId || !$leagueId || !$season) {
                echo json_encode(['error' => 'Parametri mancanti (team, league, season).']);
                return;
            }

            $model = new TeamStats();

            // Se c'è una data specifica, non ha scadenza (è uno snapshot storico)
            // Se non c'è, usiamo il TTL di 24 ore
            $ttl = $date ? 999999 : 24;

            if ($model->needsRefresh($teamId, $leagueId, $season, $ttl, $date)) {
                $this->sync($teamId, $leagueId, $season, $date);
            }

            $stats = $model->get($teamId, $leagueId, $season, $date);

            // Recupera info base squadra e lega per la vista
            $team = (new Team())->getById($teamId);
            $league = (new League())->getById($leagueId);

            echo json_encode([
                'response' => $stats ? json_decode($stats['full_stats_json'], true) : null,
                'team' => $team,
                'league' => $league,
                'season' => $season
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza le statistiche dall'API al Database
     */
    private function sync($teamId, $leagueId, $season, $date = null)
    {
        $api = new FootballApiService();
        $model = new TeamStats();

        $data = $api->fetchTeamStatistics($teamId, $leagueId, $season, $date);

        if (isset($data['response']) && !empty($data['response'])) {
            $model->save($teamId, $leagueId, $season, $data['response'], $date);
        }
    }
}

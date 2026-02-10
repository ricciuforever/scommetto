<?php
// app/GiaNik/Controllers/MatchDataController.php

namespace App\GiaNik\Controllers;

use App\Services\FootballDataService;
use App\Services\FootballApiService;

class MatchDataController
{
    private $footballData;
    private $api;

    public function __construct()
    {
        $this->footballData = new FootballDataService();
        $this->api = new FootballApiService();
    }

    /**
     * GET /api/match-details/{fixtureId}
     * Restituisce TUTTI i dati del match per la modale completa
     */
    public function getMatchDetails($fixtureId)
    {
        header('Content-Type: application/json');

        try {
            $fixture = $this->footballData->getFixtureDetails($fixtureId);
            if (!$fixture) {
                http_response_code(404);
                echo json_encode(['error' => 'Match not found']);
                return;
            }

            $status = $fixture['status_short'] ?? 'NS';

            // Raccogli tutti i dati
            $data = [
                'fixture' => $fixture,
                'statistics' => $this->footballData->getFixtureStatistics($fixtureId, $status),
                'events' => $this->footballData->getFixtureEvents($fixtureId, $status),
                'lineups' => $this->footballData->getFixtureLineups($fixtureId, $status),
                'h2h' => $this->footballData->getH2H($fixture['team_home_id'], $fixture['team_away_id']),
                'standings' => null,
                'predictions' => $this->footballData->getFixturePredictions($fixtureId, $status)
            ];

            // Standings se disponibili
            if ($fixture['league_id'] ?? null) {
                $season = \App\Config\Config::getCurrentSeason();
                $data['standings'] = $this->footballData->getStandings($fixture['league_id'], $season);
            }

            echo json_encode($data);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/player-details/{playerId}
     * Restituisce dati giocatore per la modale
     */
    public function getPlayerDetails($playerId)
    {
        header('Content-Type: application/json');

        try {
            $season = \App\Config\Config::getCurrentSeason();
            $player = $this->footballData->getPlayer($playerId, $season);

            if (!$player) {
                http_response_code(404);
                echo json_encode(['error' => 'Player not found']);
                return;
            }

            echo json_encode($player);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/team-details/{teamId}
     * Restituisce dati squadra per la modale
     */
    public function getTeamDetails($teamId)
    {
        header('Content-Type: application/json');

        try {
            $team = $this->footballData->getTeamDetails($teamId);

            if (!$team) {
                http_response_code(404);
                echo json_encode(['error' => 'Team not found']);
                return;
            }

            // Aggiungi stats stagione se disponibili
            $season = \App\Config\Config::getCurrentSeason();
            $team['season_stats'] = null; // TODO: implementare se necessario

            echo json_encode($team);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

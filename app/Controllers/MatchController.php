<?php
// app/Controllers/MatchController.php

namespace App\Controllers;

use App\Services\GeminiService;
use App\Services\BetSettler;
use App\Config\Config;
use App\Models\Prediction;
use App\Models\Analysis;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Coach;
use App\Models\Player;

class MatchController
{
    private $geminiService;
    private $betSettler;

    public function __construct()
    {
        // Strictly no FootballApiService here anymore
        $this->geminiService = new GeminiService();
        $this->betSettler = new BetSettler();
    }

    public function index()
    {
        $file = __DIR__ . '/../Views/main.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Scommetto - Area Live</h1><p>Caricamento in corso...</p>";
        }
    }

    /**
     * Reads live matches from local cache only.
     * The Cron job is responsible for updating this file.
     */
    public function getLive()
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::LIVE_DATA_FILE;
            if (file_exists($cacheFile)) {
                echo file_get_contents($cacheFile);
            } else {
                echo json_encode(['response' => [], 'status' => 'waiting_for_sync']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Performs AI analysis using strictly local DB data.
     */
    public function analyze($id)
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::LIVE_DATA_FILE;
            $liveData = json_decode(file_exists($cacheFile) ? file_get_contents($cacheFile) : '{"response":[]}', true);
            $match = null;

            foreach ($liveData['response'] ?? [] as $m) {
                if ($m['fixture']['id'] == $id) {
                    $match = $m;
                    break;
                }
            }

            if (!$match) {
                echo json_encode(['error' => 'Partita non trovata nei dati live locali. Attendi sincronizzazione.']);
                return;
            }

            // Gemini analysis uses IntelligenceService which is already DB-only
            $prediction = $this->geminiService->analyze($match);

            try {
                $analysisModel = new Analysis();
                $analysisModel->log((int) $id, $prediction);
            } catch (\Throwable $e) {
                error_log("Error saving analysis: " . $e->getMessage());
            }

            echo json_encode([
                'fixture_id' => $id,
                'prediction' => $prediction,
                'match' => $match
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets predictions from DB only.
     */
    public function getPredictions($id)
    {
        header('Content-Type: application/json');
        try {
            $predictionModel = new Prediction();
            $data = $predictionModel->getByFixtureId((int) $id);

            if (!$data) {
                echo json_encode(['error' => 'Pronostico non ancora disponibile nel database.']);
                return;
            }
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets standings from DB only.
     */
    public function getStandings($leagueId)
    {
        header('Content-Type: application/json');
        try {
            $standingModel = new Standing();
            $data = $standingModel->getByLeague((int) $leagueId);

            if (empty($data)) {
                echo json_encode(['error' => 'Classifica non ancora sincronizzata nel database.']);
                return;
            }
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets team, coach and squad from DB only.
     */
    public function getTeamDetails($teamId)
    {
        header('Content-Type: application/json');
        try {
            $teamModel = new Team();
            $coachModel = new Coach();
            $playerModel = new Player();

            $team = $teamModel->getById((int) $teamId);
            $coach = $coachModel->getByTeam((int) $teamId);
            $squad = $playerModel->getByTeam((int) $teamId);

            if (!$team) {
                echo json_encode(['error' => 'Dati squadra non presenti nel database. Attendi il cron sync.']);
                return;
            }

            echo json_encode([
                'team' => $team,
                'coach' => $coach,
                'squad' => $squad
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets player details from DB only.
     */
    public function getPlayerDetails($playerId)
    {
        header('Content-Type: application/json');
        try {
            $playerModel = new Player();
            $player = $playerModel->getById((int) $playerId);

            if (!$player) {
                echo json_encode(['error' => 'Dettagli giocatore non presenti nel database.']);
                return;
            }
            echo json_encode($player);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

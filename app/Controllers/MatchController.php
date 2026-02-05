<?php
// app/Controllers/MatchController.php

namespace App\Controllers;

use App\Services\FootballApiService;
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
    private $apiService;
    private $geminiService;
    private $betSettler;

    public function __construct()
    {
        $this->apiService = new FootballApiService();
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

    public function getLive()
    {
        header('Content-Type: application/json');
        $cacheFile = Config::LIVE_DATA_FILE;
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
            echo file_get_contents($cacheFile);
            return;
        }

        $data = $this->apiService->fetchLiveMatches();
        if (!isset($data['error'])) {
            file_put_contents($cacheFile, json_encode($data));
            $this->betSettler->settleFromLive($data['response'] ?? []);
        }
        echo json_encode($data);
    }

    public function analyze($id)
    {
        header('Content-Type: application/json');
        $liveData = json_decode(file_exists(Config::LIVE_DATA_FILE) ? file_get_contents(Config::LIVE_DATA_FILE) : '{"response":[]}', true);
        $match = null;

        foreach ($liveData['response'] ?? [] as $m) {
            if ($m['fixture']['id'] == $id) {
                $match = $m;
                break;
            }
        }

        if (!$match) {
            echo json_encode(['error' => 'Match not found in live data']);
            return;
        }

        // Fetch Predictions for AI
        $predictionModel = new Prediction();
        if ($predictionModel->needsRefresh((int) $id)) {
            $predData = $this->apiService->fetchPredictions($id);
            if (isset($predData['response'][0])) {
                $predictionModel->save((int) $id, $predData['response'][0]);
            }
        }
        $storedPrediction = $predictionModel->getByFixtureId((int) $id);

        $prediction = $this->geminiService->analyze($match, $storedPrediction);

        try {
            $analysisModel = new Analysis();
            $analysisModel->log((int) $id, $prediction);
        } catch (\Exception $e) {
            error_log("Error saving analysis: " . $e->getMessage());
        }

        echo json_encode([
            'fixture_id' => $id,
            'prediction' => $prediction,
            'match' => $match
        ]);
    }

    public function getPredictions($id)
    {
        header('Content-Type: application/json');
        $predictionModel = new Prediction();

        if ($predictionModel->needsRefresh((int) $id)) {
            $data = $this->apiService->fetchPredictions($id);
            if (isset($data['response'][0])) {
                $predictionModel->save((int) $id, $data['response'][0]);
            }
        }
        echo json_encode($predictionModel->getByFixtureId((int) $id));
    }

    public function getStandings($leagueId)
    {
        header('Content-Type: application/json');
        $standingModel = new Standing();
        $season = 2025;
        if ($standingModel->needsRefresh((int) $leagueId)) {
            $data = $this->apiService->fetchStandings($leagueId, $season);
            if (isset($data['response'][0]['league']['standings'][0])) {
                $rows = $data['response'][0]['league']['standings'][0];
                $teamModel = new Team();
                foreach ($rows as $row) {
                    $teamModel->save($row['team']);
                    $standingModel->save((int) $leagueId, $row);
                }
            }
        }
        echo json_encode($standingModel->getByLeague((int) $leagueId));
    }

    public function getTeamDetails($teamId)
    {
        header('Content-Type: application/json');
        try {
            $teamModel = new Team();
            $coachModel = new Coach();
            $playerModel = new Player();

            if ($teamModel->needsRefresh((int) $teamId)) {
                $data = $this->apiService->fetchTeam($teamId);
                if (isset($data['response'][0])) {
                    $teamModel->save($data['response'][0]);
                }
                $coachData = $this->apiService->fetchCoach($teamId);
                if (isset($coachData['response'][0])) {
                    $coachModel->save($coachData['response'][0], (int) $teamId);
                }
                $squadData = $this->apiService->fetchSquad($teamId);
                if (isset($squadData['response'][0]['players'])) {
                    foreach ($squadData['response'][0]['players'] as $p) {
                        $playerModel->save($p);
                        $playerModel->linkToSquad((int) $teamId, $p, [
                            'position' => $p['position'],
                            'number' => $p['number']
                        ]);
                    }
                }
            }

            $team = $teamModel->getById((int) $teamId);
            $coach = $coachModel->getByTeam((int) $teamId);
            $squad = $playerModel->getByTeam((int) $teamId);

            echo json_encode([
                'team' => $team,
                'coach' => $coach,
                'squad' => $squad
            ]);
        } catch (\Exception $e) {
            error_log("Error in getTeamDetails: " . $e->getMessage());
            echo json_encode([
                'error' => 'Si è verificato un errore nel recupero dei dati.',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function getPlayerDetails($playerId)
    {
        header('Content-Type: application/json');
        try {
            $playerModel = new Player();
            $player = $playerModel->getById((int) $playerId);
            if (!$player) {
                $data = $this->apiService->fetchPlayer($playerId);
                if (isset($data['response'][0]['player'])) {
                    $playerModel->save($data['response'][0]['player']);
                    $player = $playerModel->getById((int) $playerId);
                }
            }
            echo json_encode($player);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

<?php
// app/Controllers/MatchController.php

namespace App\Controllers;

use App\Services\FootballApiService;
use App\Services\GeminiService;
use App\Services\BetSettler;
use App\Config\Config;

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
        // This will serve the frontend (index.html)
        $file = __DIR__ . '/../Views/main.php';
        if (file_exists($file)) {
            require $file;
        } else {
            // Fallback to a simple message if view doesn't exist yet
            echo "<h1>Scommetto - Area Live</h1><p>Caricamento in corso...</p>";
        }
    }

    public function getLive()
    {
        header('Content-Type: application/json');

        // Use cache if it's fresh (e.g., 20 seconds)
        $cacheFile = Config::LIVE_DATA_FILE;
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 20)) {
            echo file_get_contents($cacheFile);
            return;
        }

        $data = $this->apiService->fetchLiveMatches();
        if (!isset($data['error'])) {
            file_put_contents($cacheFile, json_encode($data));
            // AUTO-SETTLE BETS (Zero API Cost)
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

        $prediction = $this->geminiService->analyze($match);

        // SAVE ANALYSIS TO DB
        try {
            $analysisModel = new \App\Models\Analysis();
            $analysisModel->log((int) $id, $prediction);
        } catch (\Exception $e) {
            // Log error but don't stop the response
            error_log("Error saving analysis: " . $e->getMessage());
        }

        echo json_encode([
            'fixture_id' => $id,
            'prediction' => $prediction,
            'match' => $match
        ]);
    }

    public function getStandings($leagueId)
    {
        header('Content-Type: application/json');
        $standingModel = new \App\Models\Standing();
        $season = 2025; // Standard season for now

        if ($standingModel->needsRefresh((int) $leagueId)) {
            $data = $this->apiService->fetchStandings($leagueId, $season);
            if (isset($data['response'][0]['league']['standings'][0])) {
                $rows = $data['response'][0]['league']['standings'][0];
                $teamModel = new \App\Models\Team();
                foreach ($rows as $row) {
                    $teamModel->save($row['team']); // Minimal save
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
            $teamModel = new \App\Models\Team();
            $coachModel = new \App\Models\Coach();
            $playerModel = new \App\Models\Player();

            if ($teamModel->needsRefresh((int) $teamId)) {
                // 1. Team & Venue Info
                $data = $this->apiService->fetchTeam($teamId);
                if (isset($data['response'][0])) {
                    // Pass the whole object which contains both 'team' and 'venue'
                    $teamModel->save($data['response'][0]);
                }

                // 2. Coach
                $coachData = $this->apiService->fetchCoach($teamId);
                if (isset($coachData['response'][0])) {
                    $coachModel->save($coachData['response'][0], (int) $teamId);
                }

                // 3. Squad & Players
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
            $playerModel = new \App\Models\Player();
            $player = $playerModel->getById((int) $playerId);

            // Se non lo abbiamo o è vecchio, aggiorniamo (ma per ora basta averlo)
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

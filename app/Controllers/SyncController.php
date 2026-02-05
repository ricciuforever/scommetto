<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Usage;
use App\Models\Bet;
use App\Models\Analysis;
use App\Services\FootballApiService;
use App\Services\GeminiService;
use App\Config\Config;

class SyncController
{
    private $usageModel;
    private $betModel;
    private $analysisModel;
    private $apiService;
    private $geminiService;

    public function __construct()
    {
        $this->usageModel = new Usage();
        $this->betModel = new Bet();
        $this->analysisModel = new Analysis();
        $this->apiService = new FootballApiService();
        $this->geminiService = new GeminiService();
    }

    public function getUsage()
    {
        header('Content-Type: application/json');
        echo json_encode($this->usageModel->getLatest());
    }

    /**
     * Main bot loop to be called via Cron every minute
     */
    public function sync()
    {
        header('Content-Type: application/json');

        $results = [
            'bot_status' => 'idle',
            'scanned' => 0,
            'analyzed' => 0,
            'bets_placed' => 0,
            'settled' => 0,
            'scheduled_tasks' => []
        ];

        // 0. Run scheduled tasks (Countries, etc.)
        $results['scheduled_tasks'] = $this->runScheduledTasks();

        // 1. Scan live matches
        $live = $this->apiService->fetchLiveMatches();
        $matches = $live['response'] ?? [];
        $results['scanned'] = count($matches);

        // 2. AUTO-SETTLE for FREE using current live data
        $betSettler = new \App\Services\BetSettler();
        $results['settled'] = $betSettler->settleFromLive($matches);

        // 3. Occasionally check for orphaned bets (those no longer in live but still pending)
        // We only do this every 30 mins to save API credits
        $settleLock = Config::DATA_PATH . 'settlement.lock';
        if (!file_exists($settleLock) || (time() - filemtime($settleLock) > 1800)) {
            $results['settled'] += $this->checkSettleBets();
            touch($settleLock);
        }

        $usage = $this->usageModel->getLatest();
        if ($usage && $usage['requests_remaining'] < 20) {
            $results['bot_status'] = 'paused_low_quota';
            echo json_encode($results);
            return;
        }

        // 4. Analyze ONLY ONE match per minute as requested
        foreach ($matches as $m) {
            $fid = $m['fixture']['id'];
            $elapsed = $m['fixture']['status']['elapsed'] ?? 0;

            if ($elapsed < 10 || $elapsed > 80)
                continue;

            if ($this->betModel->isPending($fid))
                continue;

            if ($this->analysisModel->wasRecentlyChecked($fid))
                continue;

            // ANALYZE!
            $results['analyzed']++;
            $prediction = $this->geminiService->analyze($m);
            $this->analysisModel->log($fid, $prediction);

            if (preg_match('/```json\n([\s\S]*?)\n```/', $prediction, $matches_json)) {
                $betData = json_decode($matches_json[1], true);
                if ($betData) {
                    $betData['fixture_id'] = $fid;
                    $betData['match'] = $m['teams']['home']['name'] . ' vs ' . $m['teams']['away']['name'];
                    $this->betModel->create($betData);
                    $results['bets_placed']++;
                }
            }

            // CRITICAL: Limit to 1 analysis per run
            break;
        }

        $results['bot_status'] = 'completed';
        echo json_encode($results);
    }

    public function deepSync($leagueId = 135) // Default Serie A
    {
        header('Content-Type: application/json');
        $season = 2024;
        $results = ['fixtures' => 0, 'stats' => 0, 'standings' => 0];

        // 1. Sync Standings
        $standingModel = new \App\Models\Standing();
        $data = $this->apiService->fetchStandings($leagueId, $season);
        if (isset($data['response'][0]['league']['standings'][0])) {
            foreach ($data['response'][0]['league']['standings'][0] as $row) {
                $standingModel->save($leagueId, $row);
                $results['standings']++;
            }
        }

        // 2. Sync Recent Fixtures (last 50 matches for context)
        $fixtureModel = new \App\Models\Fixture();
        $ch = curl_init(Config::FOOTBALL_API_BASE_URL . "/fixtures?league=$leagueId&season=$season&last=50");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-rapidapi-host: v3.football.api-sports.io", "x-rapidapi-key: " . Config::get('FOOTBALL_API_KEY')]);
        $fixData = json_decode(curl_exec($ch), true);
        curl_close($ch);

        foreach ($fixData['response'] ?? [] as $f) {
            $fixtureModel->save($f);
            $results['fixtures']++;
        }

        // 3. Sync Team Stats (for teams in standings)
        $statsModel = new \App\Models\TeamStats();
        if (isset($data['response'][0]['league']['standings'][0])) {
            foreach ($data['response'][0]['league']['standings'][0] as $row) {
                $tid = $row['team']['id'];
                $sData = $this->apiService->request("/teams/statistics?league=$leagueId&season=$season&team=$tid");
                if (isset($sData['response'])) {
                    $statsModel->save($tid, $leagueId, $season, $sData['response']);
                    $results['stats']++;
                }
            }
        }

        echo json_encode($results);
    }

    private function runScheduledTasks()
    {
        $log = [];

        // SYNC COUNTRIES (Once every 24h)
        $countryModel = new \App\Models\Country();
        if ($countryModel->needsRefresh(24)) {
            $data = $this->apiService->fetchCountries();
            if (isset($data['response'])) {
                foreach ($data['response'] as $c) {
                    $countryModel->save($c);
                }
                $log[] = "Countries Synced: " . count($data['response']);
            }
        }

        // SYNC SEASONS (Once every 24h)
        $seasonModel = new \App\Models\Season();
        if ($seasonModel->needsRefresh(24)) {
            $data = $this->apiService->fetchSeasons();
            if (isset($data['response'])) {
                foreach ($data['response'] as $year) {
                    $seasonModel->save($year);
                }
                $log[] = "Seasons Synced: " . count($data['response']);
            }
        }

        // SYNC LEAGUES (Once every 24h)
        $leagueModel = new \App\Models\League();
        if ($leagueModel->needsRefresh(24)) {
            $data = $this->apiService->fetchLeagues();
            if (isset($data['response'])) {
                foreach ($data['response'] as $row) {
                    $leagueModel->save($row);
                }
                $log[] = "Leagues Synced: " . count($data['response']);
            }
        }

        return $log;
    }

    private function checkSettleBets()
    {
        $pending = array_filter($this->betModel->getAll(), function ($b) {
            return $b['status'] === 'pending';
        });

        $settledCount = 0;
        $betSettler = new \App\Services\BetSettler();

        foreach ($pending as $bet) {
            // Check if match is finished (fetch details)
            $details = $this->apiService->fetchFixtureDetails($bet['fixture_id']);
            $fixture = $details['response'][0] ?? null;

            if ($fixture && in_array($fixture['fixture']['status']['short'], ['FT', 'AET', 'PEN'])) {
                $betSettler->processSettlement($bet, $fixture);
                $settledCount++;
            }
        }
        return $settledCount;
    }
}

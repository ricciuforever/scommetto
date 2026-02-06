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
        try {
            echo json_encode($this->usageModel->getLatest());
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Main bot loop to be called via Cron every minute
     */
    public function sync()
    {
        header('Content-Type: application/json');
        try {
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

                if (preg_match('/```json\s*([\s\S]*?)\s*```/', $prediction, $matches_json)) {
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
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deepSync($leagueId = 135, $season = 2024)
    {
        header('Content-Type: application/json');
        try {
            $results = [
                'overview' => $this->syncLeagueOverview($leagueId, $season),
                'top_stats' => $this->syncLeagueTopStats($leagueId, $season),
                'status' => 'success'
            ];

            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function syncLeagueOverview($leagueId, $season)
    {
        $results = ['teams' => 0, 'standings' => 0, 'rounds' => 0];

        // 1. Sync Standings & Teams (Standings API provides team basic info)
        $standingModel = new \App\Models\Standing();
        $teamModel = new \App\Models\Team();
        $data = $this->apiService->fetchStandings($leagueId, $season);

        if (isset($data['response'][0]['league']['standings'])) {
            foreach ($data['response'][0]['league']['standings'] as $group) {
                foreach ($group as $row) {
                    // Save Team Info
                    $teamModel->save(['team' => $row['team']]);
                    $teamModel->linkToLeague($row['team']['id'], $leagueId, $season);
                    $results['teams']++;

                    // Save Standing
                    $standingModel->save($leagueId, $row);
                    $results['standings']++;
                }
            }
        }

        // 2. Sync Rounds
        $roundModel = new \App\Models\Round();
        $roundsData = $this->apiService->fetchLeaguesRounds($leagueId, $season);
        if (isset($roundsData['response'])) {
            foreach ($roundsData['response'] as $roundName) {
                $roundModel->save($leagueId, $season, $roundName);
                $results['rounds']++;
            }
        }

        return $results;
    }

    public function syncLeagueFixtures($leagueId, $season)
    {
        $fixtureModel = new \App\Models\Fixture();
        $data = $this->apiService->request("/fixtures?league=$leagueId&season=$season");
        $results = ['fixtures' => 0];

        if (isset($data['response'])) {
            foreach ($data['response'] as $f) {
                $fixtureModel->save($f);
                $results['fixtures']++;
            }
        }
        return $results;
    }

    public function syncLeagueTopStats($leagueId, $season)
    {
        $topStatsModel = new \App\Models\TopStats();
        $types = ['scorers', 'assists', 'yellow_cards', 'red_cards'];
        $results = [];

        foreach ($types as $type) {
            switch ($type) {
                case 'scorers':
                    $data = $this->apiService->fetchTopScorers($leagueId, $season);
                    break;
                case 'assists':
                    $data = $this->apiService->fetchTopAssists($leagueId, $season);
                    break;
                case 'yellow_cards':
                    $data = $this->apiService->fetchTopYellowCards($leagueId, $season);
                    break;
                case 'red_cards':
                    $data = $this->apiService->fetchTopRedCards($leagueId, $season);
                    break;
                default:
                    $data = null;
            }

            if ($data && isset($data['response'])) {
                $topStatsModel->save($leagueId, $season, $type, $data['response']);
                $results[$type] = count($data['response']);
            }
        }
        return $results;
    }

    public function syncTeamDetails($teamId, $leagueId, $season)
    {
        $teamModel = new \App\Models\Team();
        $statsModel = new \App\Models\TeamStats();
        $playerModel = new \App\Models\Player();

        $results = ['team' => false, 'stats' => false, 'squad' => 0];

        // 1. Full Team & Venue Details
        $tData = $this->apiService->fetchTeam($teamId);
        if (isset($tData['response'][0])) {
            $teamModel->save($tData['response'][0]);
            $results['team'] = true;
        }

        // 2. Team Stats
        $tsData = $this->apiService->fetchTeamStatistics($teamId, $leagueId, $season);
        if (isset($tsData['response'])) {
            $statsModel->save($teamId, $leagueId, $season, $tsData['response']);
            $results['stats'] = true;
        }

        // 3. Squad
        $sqData = $this->apiService->fetchSquad($teamId);
        if (isset($sqData['response'][0]['players'])) {
            foreach ($sqData['response'][0]['players'] as $p) {
                $playerModel->save($p);
                $playerModel->linkToSquad($teamId, $p, $p);
                $results['squad']++;
            }
        }

        return $results;
    }

    private function runScheduledTasks()
    {
        $log = [];
        try {
            // SYNC COUNTRIES (Once every 24h)
            $countryModel = new \App\Models\Country();
            if ($countryModel->needsRefresh(24)) {
                $data = $this->apiService->fetchCountries();
                if (isset($data['response']) && is_array($data['response'])) {
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
                if (isset($data['response']) && is_array($data['response'])) {
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
                if (isset($data['response']) && is_array($data['response'])) {
                    foreach ($data['response'] as $row) {
                        $leagueModel->save($row);
                    }
                    $log[] = "Leagues Synced: " . count($data['response']);
                }
            }

            // SYNC PREMIUM LEAGUES STANDINGS & ROUNDS (Every 12h)
            $standingModel = new \App\Models\Standing();
            $season = 2024; // Could be dynamic
            foreach (Config::PREMIUM_LEAGUES as $lId) {
                if ($standingModel->needsRefresh($lId, 12)) {
                    $res = $this->syncLeagueOverview($lId, $season);
                    $log[] = "League $lId Overview Refreshed: " . json_encode($res);
                }
            }
        } catch (\Throwable $e) {
            $log[] = "Task Error: " . $e->getMessage();
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

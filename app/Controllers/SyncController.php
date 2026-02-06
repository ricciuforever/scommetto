<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Usage;
use App\Models\Bet;
use App\Models\Analysis;
use App\Models\FixtureOdds;
use App\Models\FixtureEvent;
use App\Models\FixtureLineup;
use App\Models\FixtureInjury;
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

    private function getCurrentSeason()
    {
        return Config::getCurrentSeason();
    }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    public function getUsage()
    {
        $this->sendJsonHeader();
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
        $this->sendJsonHeader();
        try {
            $results = [
                'bot_status' => 'idle',
                'scanned' => 0,
                'analyzed' => 0,
                'bets_placed' => 0,
                'settled' => 0,
                'scheduled_tasks' => []
            ];

            // 0. Run scheduled tasks (Countries, Leagues, Standings, etc.)
            $results['scheduled_tasks'] = $this->runScheduledTasks();

            // 1. Scan live matches
            $live = $this->apiService->fetchLiveMatches();
            if (!isset($live['error'])) {
                file_put_contents(Config::LIVE_DATA_FILE, json_encode($live));
            }

            $matches = $live['response'] ?? [];
            $results['scanned'] = count($matches);

            // 2. AUTO-SETTLE for FREE using current live data
            $betSettler = new \App\Services\BetSettler();
            $results['settled'] = $betSettler->settleFromLive($matches);

            // 3. Occasionally check for orphaned bets
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

                break;
            }

            $results['bot_status'] = 'completed';
            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deepSync($leagueId = 135, $season = null)
    {
    if ($season === null) $season = $this->getCurrentSeason();
    
    $this->sendJsonHeader();
        try {
            $results = [
                'overview' => $this->syncLeagueOverview($leagueId, $season),
                'top_stats' => $this->syncLeagueTopStats($leagueId, $season),
                'fixtures' => $this->syncLeagueFixtures($leagueId, $season),
                'details' => $this->syncLeagueDetails($leagueId, $season),
                'match_details' => $this->syncLeagueMatchDetails($leagueId, $season),
                'odds' => $this->syncLeagueOdds($leagueId, $season),
                'injuries' => $this->syncLeagueInjuries($leagueId, $season),
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

        // 1. Sync Standings & Teams
        $standingModel = new \App\Models\Standing();
        $teamModel = new \App\Models\Team();
        $data = $this->apiService->fetchStandings($leagueId, $season);

        if (isset($data['response'][0]['league']['standings'])) {
            foreach ($data['response'][0]['league']['standings'] as $group) {
                foreach ($group as $row) {
                    $teamModel->save(['team' => $row['team']]);
                    $teamModel->linkToLeague($row['team']['id'], $leagueId, $season);
                    $results['teams']++;

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

    public function syncLeagueDetails($leagueId, $season)
    {
        $results = ['team_stats' => 0, 'coaches' => 0, 'squads' => 0, 'errors' => []];
        $teamModel = new \App\Models\Team();
        $coachModel = new \App\Models\Coach();
        $playerModel = new \App\Models\Player();
        $teamStatsModel = new \App\Models\TeamStats();

        $standingModel = new \App\Models\Standing();
        $standings = $standingModel->getByLeague($leagueId);

        if (empty($standings)) {
            $results['errors'][] = "No standings found for league $leagueId.";
            return $results;
        }

        foreach ($standings as $row) {
            $tid = $row['team_id'];
            usleep(250000); // 250ms delay

            // 1. Team Stats
            try {
                if ($teamStatsModel->get($tid, $leagueId, $season) === false) {
                    $statsData = $this->apiService->fetchTeamStatistics($tid, $leagueId, $season);
                    if (isset($statsData['response']) && !empty($statsData['response'])) {
                        $teamStatsModel->save($tid, $leagueId, $season, $statsData['response']);
                        $results['team_stats']++;
                    }
                }
            } catch (\Throwable $e) { $results['errors'][] = "Team $tid Stats: " . $e->getMessage(); }

            // 2. Coach
            try {
                if ($coachModel->needsRefresh($tid)) {
                    $coachData = $this->apiService->fetchCoach($tid);
                    if (isset($coachData['response'][0])) {
                        $coachModel->save($coachData['response'][0], $tid);
                        $results['coaches']++;
                    }
                }
            } catch (\Throwable $e) { $results['errors'][] = "Team $tid Coach: " . $e->getMessage(); }

            // 3. Squad
            try {
                $squadData = $this->apiService->fetchSquad($tid);
                if (isset($squadData['response'][0]['players'])) {
                    foreach ($squadData['response'][0]['players'] as $p) {
                        $playerModel->save($p);
                        $playerModel->linkToSquad($tid, $p, $p);
                        $results['squads']++;
                    }
                }
            } catch (\Throwable $e) { $results['errors'][] = "Team $tid Squad: " . $e->getMessage(); }
        }
        return $results;
    }

    public function syncLeagueMatchDetails($leagueId, $season)
    {
        $results = ['predictions' => 0, 'h2h' => 0, 'errors' => []];
        $predictionModel = new \App\Models\Prediction();
        $h2hModel = new \App\Models\H2H();

        $from = date('Y-m-d');
        $to = date('Y-m-d', strtotime('+7 days'));
        $data = $this->apiService->request("/fixtures?league=$leagueId&season=$season&from=$from&to=$to");

        if (isset($data['response'])) {
            foreach ($data['response'] as $f) {
                $fid = $f['fixture']['id'];
                $h1 = $f['teams']['home']['id'];
                $a1 = $f['teams']['away']['id'];
                usleep(250000);

                // 1. Predictions
                try {
                    if ($predictionModel->needsRefresh($fid)) {
                        $predData = $this->apiService->fetchPredictions($fid);
                        if (isset($predData['response'][0])) {
                            $predictionModel->save($fid, $predData['response'][0]);
                            $results['predictions']++;
                        }
                    }
                } catch (\Throwable $e) { $results['errors'][] = "Fixture $fid Pred: " . $e->getMessage(); }

                // 2. H2H
                try {
                    if ($h2hModel->get($h1, $a1) === false) {
                        $h2hData = $this->apiService->fetchH2H("$h1-$a1");
                        if (isset($h2hData['response'])) {
                            $h2hModel->save($h1, $a1, $h2hData['response']);
                            $results['h2h']++;
                        }
                    }
                } catch (\Throwable $e) { $results['errors'][] = "H2H $h1-$a1: " . $e->getMessage(); }
            }
        }
        return $results;
    }

    public function syncLeagueOdds($leagueId, $season)
    {
        $results = ['fixtures_with_odds' => 0, 'errors' => []];
        $oddsModel = new \App\Models\FixtureOdds();

        $data = $this->apiService->fetchOdds(['league' => $leagueId, 'season' => $season]);

        if (isset($data['response'])) {
            foreach ($data['response'] as $row) {
                $fid = $row['fixture']['id'];
                foreach ($row['bookmakers'] as $bm) {
                    foreach ($bm['bets'] as $bet) {
                        $oddsModel->save($fid, $bm['id'], $bet['id'], $bet['values']);
                    }
                }
                $results['fixtures_with_odds']++;
            }
        }
        return $results;
    }

    public function syncLeagueInjuries($leagueId, $season)
    {
        $results = ['injuries' => 0, 'errors' => []];
        $injuryModel = new \App\Models\FixtureInjury();

        $data = $this->apiService->request("/injuries?league=$leagueId&season=$season");

        if (isset($data['response'])) {
            $processedFixtures = [];
            foreach ($data['response'] as $row) {
                $fid = $row['fixture']['id'];
                if (!in_array($fid, $processedFixtures)) {
                    $injuryModel->deleteByFixture($fid);
                    $processedFixtures[] = $fid;
                }
                $injuryModel->save($fid, $row);
                $results['injuries']++;
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
            usleep(250000);
            $data = null;
            switch ($type) {
                case 'scorers': $data = $this->apiService->fetchTopScorers($leagueId, $season); break;
                case 'assists': $data = $this->apiService->fetchTopAssists($leagueId, $season); break;
                case 'yellow_cards': $data = $this->apiService->fetchTopYellowCards($leagueId, $season); break;
                case 'red_cards': $data = $this->apiService->fetchTopRedCards($leagueId, $season); break;
            }

            if ($data && isset($data['response'])) {
                $topStatsModel->save($leagueId, $season, $type, $data['response']);
                $results[$type] = count($data['response']);
            }
        }
        return $results;
    }

    private function runScheduledTasks()
    {
    $log = [];
    try {
        $season = $this->getCurrentSeason(); // Diventa dinamico qui
        
        $minute = (int)date('i');

            // 1. GLOBAL SYNC (Once every 24h)
            $countryModel = new \App\Models\Country();
            if ($countryModel->needsRefresh(24)) {
                $data = $this->apiService->fetchCountries();
                if (isset($data['response'])) {
                    foreach ($data['response'] as $c) $countryModel->save($c);
                    $log[] = "Countries Synced";
                    return $log;
                }
            }

            $leagueModel = new \App\Models\League();
            if ($leagueModel->needsRefresh(6)) {
                $data = $this->apiService->fetchLeagues();
                if (isset($data['response'])) {
                    foreach ($data['response'] as $row) $leagueModel->save($row);
                    $log[] = "Leagues Synced";
                    return $log;
                }
            }

            // 2. LEAGUE SPECIFIC ROTATION
            $leagues = Config::PREMIUM_LEAGUES;
            $lId = $leagues[$minute % count($leagues)];

            // Every minute we do something for ONE league
            $taskType = $minute % 6;

            switch($taskType) {
                case 0: // Standings & Overview
                    $log[] = "L$lId: Overview Refreshed: " . json_encode($this->syncLeagueOverview($lId, $season));
                    break;
                case 1: // Top Stats
                    $log[] = "L$lId: Top Stats Refreshed: " . json_encode($this->syncLeagueTopStats($lId, $season));
                    break;
                case 2: // Fixtures
                    $log[] = "L$lId: Fixtures Refreshed: " . json_encode($this->syncLeagueFixtures($lId, $season));
                    break;
                case 3: // Match Details (Preds/H2H)
                    $log[] = "L$lId: Match Details Refreshed: " . json_encode($this->syncLeagueMatchDetails($lId, $season));
                    break;
                case 4: // Odds & Injuries
                    $log[] = "L$lId: Odds: " . json_encode($this->syncLeagueOdds($lId, $season));
                    $log[] = "L$lId: Injuries: " . json_encode($this->syncLeagueInjuries($lId, $season));
                    break;
                case 5: // League Details (Squads, Coaches, Team Stats)
                    $log[] = "L$lId: Deep Details Refreshed: " . json_encode($this->syncLeagueDetails($lId, $season));
                    break;
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
            usleep(250000);
        }
        return $settledCount;
    }
}

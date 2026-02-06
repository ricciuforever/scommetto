<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Usage;
use App\Models\Bet;
use App\Models\Analysis;
use App\Models\Fixture;
use App\Models\FixtureOdds;
use App\Models\FixtureEvent;
use App\Models\FixtureLineup;
use App\Models\FixtureInjury;
use App\Models\FixtureStatistics;
use App\Models\LiveOdds;
use App\Models\Country;
use App\Models\League;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Coach;
use App\Models\Player;
use App\Models\TopStats;
use App\Models\Prediction;
use App\Models\Round;
use App\Models\H2H;
use App\Models\Trophy;
use App\Models\Transfer;
use App\Models\Sidelined;
use App\Models\PlayerStatistics;
use App\Services\FootballApiService;
use App\Services\GeminiService;
use App\Config\Config;
use App\Services\Database;
use PDO;

class SyncController
{
    private $usageModel;
    private $betModel;
    private $analysisModel;
    private $fixtureModel;
    private $predictionModel;
    private $leagueModel;
    private $teamModel;
    private $coachModel;
    private $playerModel;
    private $standingModel;
    private $injuryModel;
    private $eventModel;
    private $statModel;
    private $liveOddsModel;
    private $trophyModel;
    private $transferModel;
    private $sidelinedModel;
    private $playerStatModel;
    private $apiService;
    private $geminiService;

    public function __construct()
    {
        $this->usageModel = new Usage();
        $this->betModel = new Bet();
        $this->analysisModel = new Analysis();
        $this->fixtureModel = new Fixture();
        $this->predictionModel = new Prediction();
        $this->leagueModel = new League();
        $this->teamModel = new Team();
        $this->coachModel = new Coach();
        $this->playerModel = new Player();
        $this->standingModel = new Standing();
        $this->injuryModel = new FixtureInjury();
        $this->eventModel = new FixtureEvent();
        $this->statModel = new FixtureStatistics();
        $this->liveOddsModel = new LiveOdds();
        $this->trophyModel = new Trophy();
        $this->transferModel = new Transfer();
        $this->sidelinedModel = new Sidelined();
        $this->playerStatModel = new PlayerStatistics();
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

    private function handleException(\Throwable $e)
    {
        echo json_encode([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

    public function getUsage()
    {
        $this->sendJsonHeader();
        try {
            echo json_encode($this->usageModel->getLatest());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * CRON LIVE - Ogni minuto
     */
    public function syncLive()
    {
        $this->sendJsonHeader();
        try {
            $db = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $hasToday = $db->query("SELECT COUNT(*) FROM fixtures WHERE DATE(date) = '$today'")->fetchColumn();

            if (!$hasToday) {
                echo "DB empty for today. Forcing fixture sync...\n";
                $this->syncHourly();
            }

            if (!$this->fixtureModel->hasActiveOrUpcoming()) {
                echo "Idle: No active or upcoming fixtures.\n";
                return;
            }

            $results = ['scanned' => 0, 'analyzed' => 0, 'bets_placed' => 0, 'settled' => 0, 'predictions' => 0];

            $live = $this->apiService->fetchLiveMatches();
            if (isset($live['error'])) throw new \Exception($live['error']);

            file_put_contents(Config::LIVE_DATA_FILE, json_encode($live));
            $matches = $live['response'] ?? [];
            $results['scanned'] = count($matches);

            if (empty($matches)) {
                echo "No matches currently live according to API. Checking DB for settlement...\n";
                $betSettler = new \App\Services\BetSettler();
                $results['settled'] = $betSettler->settleFromDatabase();
                echo json_encode($results);
                return;
            }

            $liveOddsData = $this->apiService->fetchLiveOdds();
            if (isset($liveOddsData['response'])) {
                foreach ($liveOddsData['response'] as $lo) {
                    $this->liveOddsModel->save($lo['fixture']['id'], $lo);
                }
            }

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $oldFixture = $this->fixtureModel->getById($fid);
                $this->fixtureModel->save($m);

                $scoreChanged = ($oldFixture && ($oldFixture['score_home'] !== $m['goals']['home'] || $oldFixture['score_away'] !== $m['goals']['away']));
                $justFinished = ($oldFixture && $oldFixture['status_short'] !== 'FT' && $m['fixture']['status']['short'] === 'FT');
                $needsUpdate = !$oldFixture || $scoreChanged || $justFinished || (time() - strtotime($oldFixture['last_detailed_update'] ?? '2000-01-01')) > 300;

                if ($needsUpdate) {
                    echo "Updating details for fixture $fid...\n";

                    $eventsData = $this->apiService->fetchFixtureEvents($fid);
                    if (isset($eventsData['response'])) {
                        $this->eventModel->deleteByFixture($fid);
                        foreach ($eventsData['response'] as $ev) {
                            if (isset($ev['team']['id'])) $this->eventModel->save($fid, $ev);
                        }
                    }

                    $statsData = $this->apiService->fetchFixtureStatistics($fid);
                    if (isset($statsData['response'])) {
                        foreach ($statsData['response'] as $st) {
                            if (isset($st['team']['id'])) $this->statModel->save($fid, $st['team']['id'], $st['statistics']);
                        }
                    }

                    if ($this->leagueModel->supportsPredictions($m['league']['id'] ?? 0)) {
                        if ($this->predictionModel->needsRefresh($fid, 1)) {
                            $predData = $this->apiService->fetchPredictions($fid);
                            if (isset($predData['response'][0])) {
                                $this->predictionModel->save($fid, $predData['response'][0]);
                                $results['predictions']++;
                            } else { echo "Fixture $fid: No predictions found.\n"; }
                        }
                    } else { echo "Fixture $fid: League coverage doesn't support predictions.\n"; }

                    $this->fixtureModel->updateDetailedTimestamp($fid);
                    usleep(250000);
                }
            }

            $betSettler = new \App\Services\BetSettler();
            $results['settled'] = $betSettler->settleFromLive($matches);
            $results['settled'] += $betSettler->settleFromDatabase();

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $elapsed = $m['fixture']['status']['elapsed'] ?? 0;
                if ($elapsed < 10 || $elapsed > 80) continue;
                if ($this->betModel->isPending($fid)) continue;
                if ($this->analysisModel->wasRecentlyChecked($fid)) continue;

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
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    /**
     * CRON HOURLY - Ogni ora
     */
    public function syncHourly()
    {
        $this->sendJsonHeader();
        $season = $this->getCurrentSeason();
        $results = ['leagues' => 0, 'standings' => 0, 'fixtures' => 0, 'injuries' => 0, 'orphans_checked' => 0, 'stats_updated' => 0];

        try {
            // Check for long-pending bets first
            $results['orphans_checked'] = $this->checkOrphanBets();

            // Sync missing stats for recently finished matches
            $results['stats_updated'] = $this->syncRecentFinishedStats();

            $db = Database::getInstance()->getConnection();
            $leaguesData = $this->apiService->fetchLeagues();
            if (isset($leaguesData['response'])) {
                foreach ($leaguesData['response'] as $row) {
                    $this->leagueModel->save($row);
                    $results['leagues']++;
                }
            }

            $today = date('Y-m-d');
            $fixturesData = $this->apiService->request("/fixtures?date=$today");
            if (isset($fixturesData['response'])) {
                foreach ($fixturesData['response'] as $f) {
                    $this->fixtureModel->save($f);
                    $results['fixtures']++;
                }
            }

            $activeLeagues = $db->query("SELECT DISTINCT league_id FROM fixtures WHERE DATE(date) = '$today'")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($activeLeagues as $lId) {
                if (!$lId) continue;
                $stData = $this->apiService->fetchStandings($lId, $season);
                if (isset($stData['response'][0]['league']['standings'])) {
                    foreach ($stData['response'][0]['league']['standings'] as $group) {
                        foreach ($group as $row) {
                            if (isset($row['team']['id'])) $this->standingModel->save($lId, $row);
                        }
                    }
                    $results['standings']++;
                }

                $injData = $this->apiService->request("/injuries?league=$lId&season=$season");
                if (isset($injData['response'])) {
                    foreach ($injData['response'] as $row) {
                        if (isset($row['fixture']['id']) && isset($row['team']['id'])) {
                            $this->injuryModel->save($row['fixture']['id'], $row);
                        }
                    }
                    $results['injuries']++;
                }
                usleep(250000);
            }

            // Settle bets after updating standings and fixtures
            $betSettler = new \App\Services\BetSettler();
            $results['settled'] = $betSettler->settleFromDatabase();

            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    /**
     * CRON 3 HOURS - Ogni 3 ore
     */
    public function sync3Hours()
    {
        $this->sendJsonHeader();
        $results = ['fixtures_processed' => 0];
        try {
            $db = Database::getInstance()->getConnection();
            $fixtures = $db->query("SELECT id FROM fixtures
                                    WHERE date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                                    AND status_short = 'NS'
                                    ORDER BY date ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($fixtures as $fid) {
                $oddsData = $this->apiService->fetchOdds(['fixture' => $fid]);
                if (isset($oddsData['response'])) {
                    foreach ($oddsData['response'] as $row) {
                        foreach ($row['bookmakers'] as $bm) {
                            foreach ($bm['bets'] as $bet) {
                                (new FixtureOdds())->save($fid, $bm['id'], $bet['id'], $bet['values']);
                            }
                        }
                    }
                    $results['fixtures_processed']++;
                }
                usleep(250000);
            }
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    /**
     * CRON DAILY - Ogni giorno
     */
    public function syncDaily()
    {
        $this->sendJsonHeader();
        $season = $this->getCurrentSeason();
        $results = ['countries' => 0, 'teams' => 0, 'coaches' => 0, 'squads' => 0, 'predictions' => 0, 'trophies' => 0, 'transfers' => 0, 'sidelined' => 0, 'player_stats' => 0];

        try {
            $db = Database::getInstance()->getConnection();
            $countryModel = new Country();
            $cData = $this->apiService->fetchCountries();
            if (isset($cData['response'])) {
                foreach ($cData['response'] as $c) { $countryModel->save($c); $results['countries']++; }
            }

            $relevantLeagues = $db->query("SELECT DISTINCT league_id FROM fixtures
                                           WHERE date BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_ADD(NOW(), INTERVAL 30 DAY)")
                                  ->fetchAll(PDO::FETCH_COLUMN);

            if (empty($relevantLeagues)) $relevantLeagues = Config::PREMIUM_LEAGUES;

            foreach ($relevantLeagues as $lId) {
                if (!$lId) continue;
                $tData = $this->apiService->fetchTeams(['league' => $lId, 'season' => $season]);
                if (isset($tData['response'])) {
                    foreach ($tData['response'] as $row) {
                        $this->teamModel->save($row);
                        $results['teams']++;
                        $tid = $row['team']['id'];

                        if ($this->coachModel->needsRefresh($tid)) {
                            $coData = $this->apiService->fetchCoach($tid);
                            if (isset($coData['response'][0])) {
                                $coach = $coData['response'][0];
                                $this->coachModel->save($coach, $tid);
                                $results['coaches']++;

                                // Sync trophies for the coach (highly relevant data)
                                if (isset($coach['id']) && $this->trophyModel->needsRefresh($coach['id'], 'coach')) {
                                    $trData = $this->apiService->fetchTrophies(['coach' => $coach['id']]);
                                    if (isset($trData['response'])) {
                                        $this->trophyModel->saveForCoach($coach['id'], $trData['response']);
                                        $results['trophies']++;
                                    }
                                    usleep(200000);
                                }

                                if (isset($coach['id']) && $this->sidelinedModel->needsRefresh($coach['id'], 'coach')) {
                                    $sdData = $this->apiService->fetchSidelined(['coach' => $coach['id']]);
                                    if (isset($sdData['response'])) {
                                        $this->sidelinedModel->saveForCoach($coach['id'], $sdData['response']);
                                        $results['sidelined']++;
                                    }
                                    usleep(200000);
                                }
                            }
                        }

                        $sqData = $this->apiService->fetchSquad($tid);
                        if (isset($sqData['response'][0]['players'])) {
                            foreach ($sqData['response'][0]['players'] as $p) {
                                $this->playerModel->save($p);
                                $this->playerModel->linkToSquad($tid, $p, $p);
                                $results['squads']++;
                            }
                        }

                        // Sync Player Statistics for the team
                        if ($this->playerStatModel->needsRefresh($tid, $season)) {
                            $page = 1;
                            do {
                                $psData = $this->apiService->fetchPlayers(['team' => $tid, 'season' => $season, 'page' => $page]);
                                if (isset($psData['response'])) {
                                    foreach ($psData['response'] as $row) {
                                        if (isset($row['player']['id']) && isset($row['statistics'][0])) {
                                            $this->playerStatModel->save(
                                                $row['player']['id'],
                                                $tid,
                                                $row['statistics'][0]['league']['id'] ?? $lId,
                                                $season,
                                                $row['statistics']
                                            );
                                            $results['player_stats']++;

                                            $pid = $row['player']['id'];
                                            // Sync trophies, sidelined, and transfers only for players with stats (key players)
                                            if ($results['trophies'] < 50 && $this->trophyModel->needsRefresh($pid, 'player', 90)) {
                                                $trData = $this->apiService->fetchTrophies(['player' => $pid]);
                                                if (isset($trData['response'])) {
                                                    $this->trophyModel->saveForPlayer($pid, $trData['response']);
                                                    $results['trophies']++;
                                                }
                                                usleep(150000);
                                            }
                                            if ($results['sidelined'] < 50 && $this->sidelinedModel->needsRefresh($pid, 'player', 90)) {
                                                $sdData = $this->apiService->fetchSidelined(['player' => $pid]);
                                                if (isset($sdData['response'])) {
                                                    $this->sidelinedModel->saveForPlayer($pid, $sdData['response']);
                                                    $results['sidelined']++;
                                                }
                                                usleep(150000);
                                            }
                                            if ($results['transfers'] < 50 && $this->transferModel->needsRefresh($pid, 90)) {
                                                $transData = $this->apiService->fetchTransfers(['player' => $pid]);
                                                if (isset($transData['response'][0]['transfers'])) {
                                                    $this->transferModel->saveForPlayer($pid, $transData['response'][0]['transfers']);
                                                    $results['transfers']++;
                                                }
                                                usleep(150000);
                                            }
                                        }
                                    }
                                    $page++;
                                } else { $page = 0; }
                            } while ($page > 1 && $page <= ($psData['paging']['total'] ?? 1));
                            usleep(250000);
                        }
                        usleep(250000);
                    }
                }

                $this->syncLeagueTopStats($lId, $season);

                if ($this->leagueModel->supportsPredictions($lId)) {
                    $upcomingFixtures = $db->query("SELECT id FROM fixtures WHERE league_id = $lId AND date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY) AND status_short = 'NS' LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($upcomingFixtures as $fid) {
                        if ($this->predictionModel->needsRefresh($fid, 24)) {
                            $predData = $this->apiService->fetchPredictions($fid);
                            if (isset($predData['response'][0])) {
                                $this->predictionModel->save($fid, $predData['response'][0]);
                                $results['predictions']++;
                            }
                            usleep(250000);
                        }
                    }
                }
            }
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    /**
     * CRON WEEKLY - Ogni settimana
     */
    public function syncWeekly()
    {
        $this->sendJsonHeader();
        $results = ['players_updated' => 0];
        try {
            $db = Database::getInstance()->getConnection();
            $players = $db->query("SELECT id FROM players ORDER BY last_updated ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($players as $pid) {
                if (!$pid) continue;
                $pData = $this->apiService->fetchPlayers(['id' => $pid, 'season' => $this->getCurrentSeason()]);
                if (isset($pData['response'][0])) {
                    $this->playerModel->save($pData['response'][0]['player']);
                    $results['players_updated']++;
                }
                usleep(250000);
            }
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function sync() { $this->syncLive(); }

    private function syncRecentFinishedStats()
    {
        $db = Database::getInstance()->getConnection();
        $today = date('Y-m-d');

        // Trova i match finiti oggi che non hanno statistiche nel DB
        $sql = "SELECT DISTINCT f.id FROM fixtures f
                LEFT JOIN fixture_statistics s ON f.id = s.fixture_id
                WHERE f.date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND f.status_short IN ('FT', 'AET', 'PEN')
                AND s.fixture_id IS NULL
                LIMIT 30";

        $fixtures = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        foreach ($fixtures as $fid) {
            $statsData = $this->apiService->fetchFixtureStatistics($fid);
            if (isset($statsData['response'])) {
                foreach ($statsData['response'] as $st) {
                    if (isset($st['team']['id'])) {
                        $this->statModel->save($fid, $st['team']['id'], $st['statistics']);
                        $count++;
                    }
                }
            }
            usleep(250000);
        }
        return $count;
    }

    private function checkOrphanBets()
    {
        $pending = array_filter($this->betModel->getAll(), function ($b) {
            // Pending for more than 2 hours
            return $b['status'] === 'pending' && (time() - strtotime($b['timestamp'])) > 7200;
        });

        if (empty($pending)) return 0;

        $count = 0;
        $betSettler = new \App\Services\BetSettler();

        foreach ($pending as $bet) {
            $fid = $bet['fixture_id'];
            $data = $this->apiService->fetchFixtureDetails($fid);
            if (isset($data['response'][0])) {
                $this->fixtureModel->save($data['response'][0]);
                if ($betSettler->processSettlement($bet, $data['response'][0])) {
                    $count++;
                }
            }
            usleep(250000);
        }
        return $count;
    }

    public function syncLeagueTopStats($leagueId, $season)
    {
        $topStatsModel = new TopStats();
        $types = ['scorers', 'assists', 'yellow_cards', 'red_cards'];
        foreach ($types as $type) {
            $data = null;
            switch ($type) {
                case 'scorers': $data = $this->apiService->fetchTopScorers($leagueId, $season); break;
                case 'assists': $data = $this->apiService->fetchTopAssists($leagueId, $season); break;
                case 'yellow_cards': $data = $this->apiService->fetchTopYellowCards($leagueId, $season); break;
                case 'red_cards': $data = $this->apiService->fetchTopRedCards($leagueId, $season); break;
            }
            if ($data && isset($data['response'])) $topStatsModel->save($leagueId, $season, $type, $data['response']);
            usleep(200000);
        }
    }

    public function deepSync($leagueId = 135, $season = null)
    {
        if ($season === null) $season = $this->getCurrentSeason();
        $this->sendJsonHeader();
        try {
            $results = [
                'overview' => $this->syncLeagueOverview($leagueId, $season),
                'fixtures' => $this->syncLeagueFixtures($leagueId, $season),
                'status' => 'success'
            ];
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function syncLeagueOverview($leagueId, $season)
    {
        $data = $this->apiService->fetchStandings($leagueId, $season);
        if (isset($data['response'][0]['league']['standings'])) {
            foreach ($data['response'][0]['league']['standings'] as $group) {
                foreach ($group as $row) {
                    if (isset($row['team']['id'])) {
                        $this->teamModel->save(['team' => $row['team']]);
                        $this->standingModel->save($leagueId, $row);
                    }
                }
            }
        }
        return ['status' => 'completed'];
    }

    public function syncLeagueFixtures($leagueId, $season)
    {
        $data = $this->apiService->request("/fixtures?league=$leagueId&season=$season");
        if (isset($data['response'])) {
            foreach ($data['response'] as $f) $this->fixtureModel->save($f);
        }
        return ['status' => 'completed'];
    }
}

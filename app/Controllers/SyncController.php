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
use App\Models\PlayerSeason;
use App\Models\FixturePlayerStatistics;
use App\Models\FixtureLineupModel;
use App\Models\Bookmaker;
use App\Models\BetType;
use App\Models\Venue;
use App\Models\TeamStats;
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
    private $playerSeasonModel;
    private $fixturePlayerStatModel;
    private $fixtureLineupModel;
    private $bookmakerModel;
    private $betTypeModel;
    private $countryModel;
    private $h2hModel;
    private $teamStatsModel;
    private $venueModel;
    private $apiService;
    private $geminiService;
    private $betfairService;

    public function __construct()
    {
        set_time_limit(300);
        ignore_user_abort(true);

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
        $this->playerSeasonModel = new PlayerSeason();
        $this->fixturePlayerStatModel = new FixturePlayerStatistics();
        $this->fixtureLineupModel = new FixtureLineup();
        $this->bookmakerModel = new Bookmaker();
        $this->betTypeModel = new BetType();
        $this->countryModel = new Country();
        $this->h2hModel = new H2H();
        $this->teamStatsModel = new TeamStats();
        $this->venueModel = new Venue();
        $this->apiService = new FootballApiService();
        $this->geminiService = new GeminiService();
        $this->betfairService = new \App\Services\BetfairService();
    }

    private function getCurrentSeason() { return Config::getCurrentSeason(); }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: application/json');
    }

    private function handleException(\Throwable $e)
    {
        echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }

    public function getUsage()
    {
        $this->sendJsonHeader();
        try { echo json_encode($this->usageModel->getLatest()); } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function syncLive()
    {
        $this->sendJsonHeader();
        try {
            $db = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $hasToday = $db->query("SELECT COUNT(*) FROM fixtures WHERE DATE(date) = '$today'")->fetchColumn();

            if (!$hasToday) $this->syncHourly();
            if (!$this->fixtureModel->hasActiveOrUpcoming()) return;

            $results = ['scanned' => 0, 'analyzed' => 0, 'bets_placed' => 0, 'settled' => 0, 'predictions' => 0];

            $live = $this->apiService->fetchLiveMatches();
            if (isset($live['error'])) throw new \Exception($live['error']);
            $allLiveMatches = $live['response'] ?? [];
            $results['scanned'] = count($allLiveMatches);

            if (empty($allLiveMatches)) {
                $betSettler = new \App\Services\BetSettler();
                $results['settled'] = $betSettler->settleFromDatabase();
                echo json_encode($results);
                return;
            }

            $liveOddsData = $this->apiService->fetchLiveOdds();
            $liveOddsIds = [];
            if (isset($liveOddsData['response'])) {
                foreach ($liveOddsData['response'] as $lo) {
                    $fid = $lo['fixture']['id'];
                    $this->liveOddsModel->save($fid, $lo);
                    $liveOddsIds[] = (int) $fid;
                }
            }

            $allFids = array_map(fn($m) => (int) $m['fixture']['id'], $allLiveMatches);
            $fidsStr = implode(',', $allFids);
            $stmt = $db->query("SELECT fixture_id, bookmaker_id FROM fixture_odds WHERE fixture_id IN ($fidsStr)");
            $bookiesRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $bookiesByFid = [];
            $preMatchOddsIds = [];
            foreach ($bookiesRaw as $row) {
                $bookiesByFid[$row['fixture_id']][] = (int) $row['bookmaker_id'];
                $preMatchOddsIds[] = (int) $row['fixture_id'];
            }
            $preMatchOddsIds = array_unique($preMatchOddsIds);

            $matches = array_filter($allLiveMatches, function ($m) {
                $status = $m['fixture']['status']['short'] ?? '';
                return !in_array($status, ['FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD', 'AWD', 'WO']);
            });

            $enrichedMatches = [];
            foreach ($matches as $m) {
                $fid = (int) $m['fixture']['id'];
                $m['available_bookmakers'] = array_values(array_unique($bookiesByFid[$fid] ?? []));
                $enrichedMatches[] = $m;
            }
            $live['response'] = $enrichedMatches;
            file_put_contents(Config::LIVE_DATA_FILE, json_encode($live));

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $this->fixtureModel->save($m);
            }

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $balance = $this->betModel->getBalanceSummary(Config::INITIAL_BANKROLL);
                if ($this->betModel->hasBet($fid)) continue;
                if ($balance['available_balance'] <= 0.50) continue;

                $prediction = $this->geminiService->analyze($m, $balance);
                $this->analysisModel->log($fid, $prediction);
                $results['analyzed']++;

                if (preg_match('/```json\s*([\s\S]*?)\s*```/', $prediction, $matches_json)) {
                    $betData = json_decode($matches_json[1], true);
                    if ($betData && isset($betData['stake']) && $betData['stake'] > 0) {
                        if ($betData['stake'] > $balance['available_balance']) continue;

                        $betData['fixture_id'] = $fid;
                        $matchName = $m['teams']['home']['name'] . ' vs ' . $m['teams']['away']['name'];
                        $betData['match'] = $matchName;

                        $confidence = (int)($betData['confidence'] ?? 0);
                        if ($this->betfairService->isConfigured() && $confidence >= Config::BETFAIR_CONFIDENCE_THRESHOLD) {
                            $marketInfo = $this->betfairService->findMarket($matchName);
                            if ($marketInfo) {
                                $selectionId = $this->betfairService->mapAdviceToSelection($betData['advice'], $marketInfo['runners']);
                                if ($selectionId) {
                                    $bfResult = $this->betfairService->placeBet($marketInfo['marketId'], $selectionId, $betData['odds'], $betData['stake']);
                                    $statusBf = isset($bfResult['status']) ? $bfResult['status'] : ($bfResult['result']['status'] ?? null);
                                    if ($statusBf === 'SUCCESS') {
                                        $betData['status'] = 'placed';
                                        $betData['betfair_id'] = $bfResult['instructionReports'][0]['betId'] ?? ($bfResult['result']['instructionReports'][0]['betId'] ?? null);
                                    }
                                }
                            }
                        }

                        $this->betModel->create($betData);
                        $results['bets_placed']++;
                    }
                }
                usleep(200000);
            }
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function syncHourly()
    {
        $this->sendJsonHeader();
        $results = ['leagues' => 0, 'standings' => 0, 'fixtures' => 0, 'injuries' => 0, 'stats_updated' => 0, 'h2h' => 0];
        try {
            $this->betModel->cleanup();
            $this->betModel->deduplicate();
            $this->refreshPendingFixtures();
            $betSettler = new \App\Services\BetSettler();
            $results['settled'] = $betSettler->settleFromDatabase();
            $results['stats_updated'] = $this->syncRecentFinishedStats();

            $today = date('Y-m-d');
            $fixturesData = $this->apiService->request("/fixtures?date=$today");
            if (isset($fixturesData['response'])) {
                foreach ($fixturesData['response'] as $f) {
                    $this->fixtureModel->save($f);
                    $results['fixtures']++;
                }
            }
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function refreshPendingFixtures()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT DISTINCT fixture_id FROM bets WHERE status = 'pending' AND fixture_id NOT IN (SELECT id FROM fixtures WHERE last_updated > DATE_SUB(NOW(), INTERVAL 15 MINUTE))";
            $fixtures = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            if (empty($fixtures)) return 0;
            $count = 0;
            $chunks = array_chunk($fixtures, 20);
            foreach ($chunks as $chunk) {
                $ids = implode('-', $chunk);
                $data = $this->apiService->request("/fixtures?ids=$ids");
                if (isset($data['response']) && is_array($data['response'])) {
                    foreach ($data['response'] as $fixtureData) { $this->fixtureModel->save($fixtureData); $count++; }
                }
                usleep(500000);
            }
            return $count;
        } catch (\Exception $e) { return 0; }
    }

    public function sync3Hours()
    {
        $this->sendJsonHeader();
        try {
            $db = Database::getInstance()->getConnection();
            $fixtures = $db->query("SELECT id, league_id FROM fixtures WHERE date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) AND status_short = 'NS' ORDER BY date ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fixtures as $f) {
                $fid = $f['id'];
                $this->apiService->fetchOdds(['fixture' => $fid]);
                usleep(250000);
            }
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function syncDaily()
    {
        $this->sendJsonHeader();
        try {
            $results = ['countries' => 0, 'teams' => 0];
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function syncWeekly()
    {
        $this->sendJsonHeader();
        try { echo json_encode(['status' => 'success']); } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function sync()
    {
        $this->syncHourly();
        $this->syncLive();
    }

    private function syncRecentFinishedStats()
    {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT DISTINCT f.id FROM fixtures f LEFT JOIN fixture_statistics s ON f.id = s.fixture_id WHERE f.date > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND f.status_short IN ('FT', 'AET', 'PEN') AND s.fixture_id IS NULL LIMIT 10";
        $fixtures = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($fixtures as $fid) {
            $statsData = $this->apiService->fetchFixtureStatistics($fid);
            if (isset($statsData['response'])) {
                foreach ($statsData['response'] as $st) {
                    if (isset($st['team']['id'])) { $this->statModel->save($fid, $st['team']['id'], $st['statistics']); $count++; }
                }
            }
            usleep(250000);
        }
        return $count;
    }

    public function deepSync($leagueId = 135, $season = null)
    {
        if ($season === null) $season = Config::getCurrentSeason();
        $this->sendJsonHeader();
        try {
            $data = $this->apiService->request("/fixtures?league=$leagueId&season=$season");
            if (isset($data['response'])) {
                foreach ($data['response'] as $f) $this->fixtureModel->save($f);
            }
            echo json_encode(['status' => 'success']);
        } catch (\Throwable $e) { $this->handleException($e); }
    }
}

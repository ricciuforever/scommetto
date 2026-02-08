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
use App\Models\Bookmaker;
use App\Models\BetType;
use App\Models\Venue;
use App\Models\TeamStats;
use App\Services\FootballApiService;
use App\Services\GeminiService;
use App\Config\Config;
use App\Services\Database;
use App\Services\BetfairService;
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
        $this->betfairService = new BetfairService();
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
            if (isset($liveOddsData['response'])) {
                foreach ($liveOddsData['response'] as $lo) {
                    $this->liveOddsModel->save($lo['fixture']['id'], $lo);
                }
            }

            $allFids = array_map(fn($m) => (int) $m['fixture']['id'], $allLiveMatches);
            $fidsStr = implode(',', $allFids);
            $stmt = $db->query("SELECT fixture_id, bookmaker_id FROM fixture_odds WHERE fixture_id IN ($fidsStr)");
            $bookiesRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $bookiesByFid = [];
            foreach ($bookiesRaw as $row) { $bookiesByFid[$row['fixture_id']][] = (int) $row['bookmaker_id']; }

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
            file_put_contents(Config::LIVE_DATA_FILE, json_encode(['response' => $enrichedMatches]));

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $this->fixtureModel->save($m);

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
                                    $res = isset($bfResult['status']) ? $bfResult : ($bfResult['result'] ?? null);
                                    if ($res && $res['status'] === 'SUCCESS') {
                                        $betData['status'] = 'placed';
                                        $reports = $res['instructionReports'] ?? ($res['result']['instructionReports'] ?? []);
                                        $betData['betfair_id'] = $reports[0]['betId'] ?? null;
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
        try {
            $this->betModel->cleanup();
            $this->betModel->deduplicate();
            $this->refreshPendingFixtures();
            $betSettler = new \App\Services\BetSettler();
            $settled = $betSettler->settleFromDatabase();
            $stats = $this->syncRecentFinishedStats();
            echo json_encode(['status' => 'success', 'settled' => $settled, 'stats_updated' => $stats]);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function refreshPendingFixtures()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT DISTINCT fixture_id FROM bets WHERE status = 'pending' AND fixture_id NOT IN (SELECT id FROM fixtures WHERE last_updated > DATE_SUB(NOW(), INTERVAL 15 MINUTE))";
            $fixtures = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            if (empty($fixtures)) return 0;
            foreach (array_chunk($fixtures, 20) as $chunk) {
                $data = $this->apiService->request("/fixtures?ids=" . implode('-', $chunk));
                if (isset($data['response'])) { foreach ($data['response'] as $f) $this->fixtureModel->save($f); }
                usleep(500000);
            }
            return count($fixtures);
        } catch (\Exception $e) { return 0; }
    }

    private function syncRecentFinishedStats()
    {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT DISTINCT f.id FROM fixtures f LEFT JOIN fixture_statistics s ON f.id = s.fixture_id WHERE f.date > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND f.status_short IN ('FT', 'AET', 'PEN') AND s.fixture_id IS NULL LIMIT 10";
        $fixtures = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fixtures as $fid) {
            $data = $this->apiService->fetchFixtureStatistics($fid);
            if (isset($data['response'])) {
                foreach ($data['response'] as $st) $this->statModel->save($fid, $st['team']['id'], $st['statistics']);
            }
            usleep(250000);
        }
        return count($fixtures);
    }

    public function sync() { $this->syncHourly(); $this->syncLive(); }
    public function syncDaily() { echo json_encode(['status' => 'success']); }
    public function syncWeekly() { echo json_encode(['status' => 'success']); }
    public function sync3Hours() { echo json_encode(['status' => 'success']); }
    public function deepSync($l, $s) { echo json_encode(['status' => 'success']); }
}

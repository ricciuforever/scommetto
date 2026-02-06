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
    private $apiService;
    private $geminiService;

    public function __construct()
    {
        $this->usageModel = new Usage();
        $this->betModel = new Bet();
        $this->analysisModel = new Analysis();
        $this->fixtureModel = new Fixture();
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

    /**
     * CRON LIVE - Ogni minuto
     * Gestisce match live, eventi, statistiche, quote live e Gemini
     */
    public function syncLive()
    {
        $this->sendJsonHeader();
        try {
            $db = Database::getInstance()->getConnection();

            // 1. Controlla se abbiamo match programmati oggi nel DB
            $today = date('Y-m-d');
            $hasToday = $db->query("SELECT COUNT(*) FROM fixtures WHERE DATE(date) = '$today'")->fetchColumn();

            if (!$hasToday) {
                echo "DB empty for today. Forcing fixture sync for $today...\n";
                $this->syncHourly(); // Chiama hourly per popolare oggi
            }

            // 2. Logica "Stay Idle"
            if (!$this->fixtureModel->hasActiveOrUpcoming()) {
                echo "Idle: No active or upcoming fixtures.\n";
                return;
            }

            $results = ['scanned' => 0, 'analyzed' => 0, 'bets_placed' => 0, 'settled' => 0];

            // 3. Fetch ALL live matches (bulk call)
            $live = $this->apiService->fetchLiveMatches();
            if (isset($live['error'])) throw new \Exception($live['error']);

            file_put_contents(Config::LIVE_DATA_FILE, json_encode($live));
            $matches = $live['response'] ?? [];
            $results['scanned'] = count($matches);

            if (empty($matches)) {
                echo "No matches currently live according to API.\n";
                return;
            }

            // 4. Fetch ALL live odds (bulk call - risparmia molti crediti!)
            $liveOddsData = $this->apiService->fetchLiveOdds();
            $liveOddsModel = new LiveOdds();
            if (isset($liveOddsData['response'])) {
                foreach ($liveOddsData['response'] as $lo) {
                    $liveOddsModel->save($lo['fixture']['id'], $lo);
                }
            }

            // 5. Aggiornamento selettivo dettagli (Events, Stats)
            $eventModel = new FixtureEvent();
            $statModel = new FixtureStatistics();

            foreach ($matches as $m) {
                $fid = $m['fixture']['id'];
                $oldFixture = $this->fixtureModel->getById($fid);

                // Salviamo lo stato base
                $this->fixtureModel->save($m);

                // Throttling: Aggiorna dettagli solo se il punteggio è cambiato
                // o se sono passati più di 5 minuti dall'ultimo aggiornamento dettagliato
                $scoreChanged = ($oldFixture && ($oldFixture['score_home'] !== $m['goals']['home'] || $oldFixture['score_away'] !== $m['goals']['away']));
                $needsUpdate = !$oldFixture || $scoreChanged || (time() - strtotime($oldFixture['last_detailed_update'] ?? '2000-01-01')) > 300;

                if ($needsUpdate) {
                    echo "Updating details for fixture $fid...\n";

                    // Eventi
                    $eventsData = $this->apiService->fetchFixtureEvents($fid);
                    if (isset($eventsData['response'])) {
                        $eventModel->deleteByFixture($fid);
                        foreach ($eventsData['response'] as $ev) $eventModel->save($fid, $ev);
                    }

                    // Statistiche
                    $statsData = $this->apiService->fetchFixtureStatistics($fid);
                    if (isset($statsData['response'])) {
                        foreach ($statsData['response'] as $st) {
                            $statModel->save($fid, $st['team']['id'], $st['statistics']);
                        }
                    }

                    $this->fixtureModel->updateDetailedTimestamp($fid);
                    usleep(250000);
                }
            }

            // 6. AUTO-SETTLE scommesse
            $betSettler = new \App\Services\BetSettler();
            $results['settled'] = $betSettler->settleFromLive($matches);

            // 7. Analisi Gemini
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
                break; // Analizza solo uno per ciclo per non saturare Gemini/API
            }

            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * CRON HOURLY - Ogni ora
     * Leghe, Classifiche, Calendario del giorno, Infortuni
     */
    public function syncHourly()
    {
        $this->sendJsonHeader();
        $season = $this->getCurrentSeason();
        $results = ['leagues' => 0, 'standings' => 0, 'fixtures' => 0, 'injuries' => 0];

        try {
            $db = Database::getInstance()->getConnection();

            // 1. Sync Leagues (Bulk - 1 call)
            $leagueModel = new League();
            $leaguesData = $this->apiService->fetchLeagues();
            if (isset($leaguesData['response'])) {
                foreach ($leaguesData['response'] as $row) {
                    $leagueModel->save($row);
                    $results['leagues']++;
                }
            }

            // 2. Sync Today's Fixtures (Bulk - 1 call per all leagues!)
            $today = date('Y-m-d');
            $fixturesData = $this->apiService->request("/fixtures?date=$today");
            if (isset($fixturesData['response'])) {
                foreach ($fixturesData['response'] as $f) {
                    $this->fixtureModel->save($f);
                    $results['fixtures']++;
                }
            }

            // 3. Standings & Injuries (Purtroppo questi richiedono league-id)
            // Ottimizzazione: solo per leghe che hanno partite oggi
            $activeLeagues = $db->query("SELECT DISTINCT league_id FROM fixtures WHERE DATE(date) = '$today'")->fetchAll(PDO::FETCH_COLUMN);

            $standingModel = new Standing();
            $injuryModel = new FixtureInjury();

            foreach ($activeLeagues as $lId) {
                // Standings
                $stData = $this->apiService->fetchStandings($lId, $season);
                if (isset($stData['response'][0]['league']['standings'])) {
                    foreach ($stData['response'][0]['league']['standings'] as $group) {
                        foreach ($group as $row) $standingModel->save($lId, $row);
                    }
                    $results['standings']++;
                }

                // Injuries (per league)
                $injData = $this->apiService->request("/injuries?league=$lId&season=$season");
                if (isset($injData['response'])) {
                    foreach ($injData['response'] as $row) $injuryModel->save($row['fixture']['id'], $row);
                    $results['injuries']++;
                }
                usleep(250000);
            }

            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * CRON 3 HOURS - Ogni 3 ore
     * Quote Pre-match
     */
    public function sync3Hours()
    {
        $this->sendJsonHeader();
        $results = ['fixtures_processed' => 0];
        try {
            $db = Database::getInstance()->getConnection();
            $oddsModel = new FixtureOdds();

            // Sync quote per i match dei prossimi 3 giorni che non hanno ancora quote o sono vecchie
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
                                $oddsModel->save($fid, $bm['id'], $bet['id'], $bet['values']);
                            }
                        }
                    }
                    $results['fixtures_processed']++;
                }
                usleep(250000);
            }
            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * CRON DAILY - Ogni giorno
     * Countries, Seasons, Team Info, Coaches, Squads, Marcatori, Predictions
     */
    public function syncDaily()
    {
        $this->sendJsonHeader();
        $season = $this->getCurrentSeason();
        $results = ['countries' => 0, 'teams' => 0, 'coaches' => 0, 'squads' => 0, 'predictions' => 0];

        try {
            $db = Database::getInstance()->getConnection();

            // 1. Countries (1 call)
            $countryModel = new Country();
            $cData = $this->apiService->fetchCountries();
            if (isset($cData['response'])) {
                foreach ($cData['response'] as $c) { $countryModel->save($c); $results['countries']++; }
            }

            // 2. Sync Teams by League (Bulk per lega)
            // Ottimizzazione: Consideriamo solo le leghe che hanno avuto match nell'ultimo mese o ne hanno in futuro
            $relevantLeagues = $db->query("SELECT DISTINCT league_id FROM fixtures
                                           WHERE date BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_ADD(NOW(), INTERVAL 30 DAY)")
                                  ->fetchAll(PDO::FETCH_COLUMN);

            if (empty($relevantLeagues)) {
                // Fallback alle leghe premium se DB è vuoto
                $relevantLeagues = Config::PREMIUM_LEAGUES;
            }

            $teamModel = new Team();
            $coachModel = new Coach();
            $playerModel = new Player();
            $predictionModel = new Prediction();

            foreach ($relevantLeagues as $lId) {
                // Teams (1 call per lega)
                $tData = $this->apiService->fetchTeams(['league' => $lId, 'season' => $season]);
                if (isset($tData['response'])) {
                    foreach ($tData['response'] as $row) {
                        $teamModel->save($row);
                        $results['teams']++;
                        $tid = $row['team']['id'];

                        // Coach & Squad: Solo se non aggiornati di recente (Throttling interno al Model o qui)
                        if ($coachModel->needsRefresh($tid)) {
                            $coData = $this->apiService->fetchCoach($tid);
                            if (isset($coData['response'][0])) { $coachModel->save($coData['response'][0], $tid); $results['coaches']++; }
                        }

                        // Squad (Giocatori)
                        $sqData = $this->apiService->fetchSquad($tid);
                        if (isset($sqData['response'][0]['players'])) {
                            foreach ($sqData['response'][0]['players'] as $p) {
                                $playerModel->save($p);
                                $playerModel->linkToSquad($tid, $p, $p);
                                $results['squads']++;
                            }
                        }
                        usleep(250000);
                    }
                }

                // Top Stats (4 calls per lega)
                $this->syncLeagueTopStats($lId, $season);

                // Predictions per i match imminenti (1 call per fixture)
                $upcomingFixtures = $db->query("SELECT id FROM fixtures WHERE league_id = $lId AND date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY) AND status_short = 'NS' LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($upcomingFixtures as $fid) {
                    $predData = $this->apiService->fetchPredictions($fid);
                    if (isset($predData['response'][0])) {
                        $predictionModel->save($fid, $predData['response'][0]);
                        $results['predictions']++;
                    }
                    usleep(250000);
                }
            }

            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
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
            // Aggiorna profili giocatori a rotazione
            $players = $db->query("SELECT id FROM players ORDER BY last_updated ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($players as $pid) {
                $pData = $this->apiService->fetchPlayers(['id' => $pid, 'season' => $this->getCurrentSeason()]);
                if (isset($pData['response'][0])) {
                    (new Player())->save($pData['response'][0]['player']);
                    $results['players_updated']++;
                }
                usleep(250000);
            }
            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function sync() { $this->syncLive(); }

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
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function syncLeagueOverview($leagueId, $season)
    {
        $standingModel = new Standing();
        $teamModel = new Team();
        $data = $this->apiService->fetchStandings($leagueId, $season);
        if (isset($data['response'][0]['league']['standings'])) {
            foreach ($data['response'][0]['league']['standings'] as $group) {
                foreach ($group as $row) {
                    $teamModel->save(['team' => $row['team']]);
                    $standingModel->save($leagueId, $row);
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

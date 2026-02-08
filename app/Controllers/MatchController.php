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
use App\Models\League;
use App\Models\Fixture;
use App\Models\TeamStats;
use App\Models\H2H;
use App\Models\FixtureEvent;
use App\Models\FixtureLineup;
use App\Models\FixtureStatistics;
use App\Models\FixtureOdds;
use App\Models\FixtureInjury;

class MatchController
{
    private $geminiService;
    private $betSettler;

    public function __construct()
    {
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
        try {
            // Semplificazione: Leggiamo i dati aggregati di Betfair salvati dal syncLive
            $cacheFile = Config::DATA_PATH . 'betfair_live.json';
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
     * Recupera tutti gli sport attivi su Betfair
     */
    public function getSports()
    {
        header('Content-Type: application/json');
        try {
            $bf = new \App\Services\BetfairService();
            $data = $bf->getEventTypes();
            echo json_encode($data['result'] ?? []);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Dati Account reali da Betfair
     */
    public function getAccount()
    {
        header('Content-Type: application/json');
        try {
            $bf = new \App\Services\BetfairService();
            $funds = $bf->getFunds();
            $statement = $bf->getAccountStatement();
            $orders = $bf->getCurrentOrders();

            echo json_encode([
                'funds' => $funds,
                'statement' => $statement['accountStatement'] ?? [],
                'orders' => $orders['currentOrders'] ?? []
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function analyze($marketId)
    {
        header('Content-Type: application/json');
        try {
            // Analisi basata su dati Betfair passati da cache o API
            $bf = new \App\Services\BetfairService();
            $cacheFile = Config::DATA_PATH . 'betfair_live.json';
            $event = null;

            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                foreach ($data['response'] ?? [] as $m) {
                    if ($m['marketId'] === $marketId) {
                        $event = $m;
                        break;
                    }
                }
            }

            if (!$event) {
                echo json_encode(['error' => 'Evento non trovato.']);
                return;
            }

            $funds = $bf->getFunds();
            $balance = [
                'available_balance' => $funds['availableToBetBalance'] ?? 0,
                'current_portfolio' => ($funds['availableToBetBalance'] ?? 0) + abs($funds['exposure'] ?? 0)
            ];

            $prediction = $this->geminiService->analyze([$event], $balance);
            echo json_encode(['prediction' => $prediction, 'match' => $event]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function dashboard()
    {
        try {
            // 1. Live Matches
            $liveMatches = [];
            $cacheFile = Config::DATA_PATH . 'betfair_live.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                // Flatten aggregated data if needed, or use as is based on SyncController structure
                // SyncController saves: ['response' => $betfairAggregated]
                $liveMatches = $data['response'] ?? [];
            }

            // 2. Predictions
            $predictions = [];
            $predProps = Config::DATA_PATH . 'betfair_hot_predictions.json';
            if (file_exists($predProps)) {
                $data = json_decode(file_get_contents($predProps), true);
                $predictions = $data['response'] ?? [];
            }

            // 3. History & Account Stats
            $db = \App\Services\Database::getInstance()->getConnection();

            // History (Last 10 bets)
            $stmt = $db->query("SELECT * FROM bets ORDER BY timestamp DESC LIMIT 10");
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Account Balance calculation (Unified Real/Sim logic)
            $account = ['available' => 0, 'exposure' => 0, 'wallet' => 0];

            if (Config::isSimulationMode()) {
                // Simulation Logic
                $stmt = $db->query("SELECT status, odds, stake FROM bets WHERE betfair_id IS NULL AND status IN ('won', 'lost')");
                $closedBets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $profit = 0;
                foreach ($closedBets as $b) {
                    if ($b['status'] === 'won')
                        $profit += $b['stake'] * ($b['odds'] - 1);
                    else
                        $profit -= $b['stake'];
                }

                $stmt = $db->query("SELECT SUM(stake) as exposure FROM bets WHERE betfair_id IS NULL AND status IN ('placed', 'pending')");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $exposure = (float) ($row['exposure'] ?? 0);

                $initial = Config::INITIAL_BANKROLL;
                $account['wallet'] = $initial + $profit;
                $account['exposure'] = $exposure;
                $account['available'] = $account['wallet'] - $exposure;
            } else {
                // Real Betfair Logic
                try {
                    $bf = new \App\Services\BetfairService();
                    if ($bf->isConfigured()) {
                        $funds = $bf->getFunds();
                        if (isset($funds['result']))
                            $funds = $funds['result']; // Handle RPC vs REST wrapper diffs

                        $account['available'] = $funds['availableToBetBalance'] ?? 0;
                        $account['exposure'] = abs($funds['exposure'] ?? 0);
                        $account['wallet'] = $account['available'] + $account['exposure'];
                    }
                } catch (\Throwable $e) { /* Ignore API errors for dashboard rendering, show 0 */
                }
            }

            // 4. Sport Extraction (for filters)
            $availableSports = [];
            foreach ($liveMatches as $m) {
                if (!empty($m['sport'])) {
                    $availableSports[$m['sport']] = true;
                }
            }
            $activeSports = array_keys($availableSports);
            sort($activeSports);

            // 5. Open Bets Count
            $openBetsCount = 0;
            if (Config::isSimulationMode()) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM bets WHERE betfair_id IS NULL AND status IN ('placed', 'pending')");
                $openBetsCount = (int) $stmt->fetchColumn();
            } else {
                // For real mode, count bets in DB that are pending/placed
                // Alternatively could try API listCurrentOrders, but DB is faster for dashboard
                $stmt = $db->query("SELECT COUNT(*) as count FROM bets WHERE betfair_id IS NOT NULL AND status IN ('placed', 'pending')");
                $openBetsCount = (int) $stmt->fetchColumn();
            }

            require __DIR__ . '/../Views/partials/dashboard.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore Dashboard: ' . $e->getMessage() . '</div>';
        }
    }

    public function getUpcoming()
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::DATA_PATH . 'betfair_upcoming.json';
            if (file_exists($cacheFile)) {
                echo file_get_contents($cacheFile);
            } else {
                echo json_encode(['response' => [], 'status' => 'waiting_for_sync']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function legacy_dashboard()
    {
        try {
            $selectedCountry = $_GET['country'] ?? 'all';
            $selectedLeague = $_GET['league'] ?? 'all';
            $selectedBookmaker = $_GET['bookmaker'] ?? 'all';

            $db = \App\Services\Database::getInstance()->getConnection();

            // 1. Get ALL Live Matches
            $cacheFile = Config::LIVE_DATA_FILE;
            $allLiveMatches = [];
            if (file_exists($cacheFile)) {
                $raw = json_decode(file_get_contents($cacheFile), true);
                $allLiveMatches = $raw['response'] ?? [];
            }

            // 2. Get ALL Potential Upcoming Matches (Next 24h with odds) to determine available filters
            $sqlAvailable = "SELECT DISTINCT l.country_name as country, l.id as league_id, l.name as league_name
                             FROM fixtures f
                             JOIN leagues l ON f.league_id = l.id
                             WHERE f.date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                             AND f.status_short = 'NS'
                             AND EXISTS (SELECT 1 FROM fixture_odds fo WHERE fo.fixture_id = f.id)";
            $availableUpcoming = $db->query($sqlAvailable)->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Aggregate Countries and Leagues for Filters
            $availableCountries = [];
            $availableLeagues = []; // league_id => [name => ..., country => ...]

            foreach ($allLiveMatches as $m) {
                $c = $m['league']['country'] ?? $m['league']['country_name'] ?? 'International';
                $availableCountries[$c] = $c;
                $availableLeagues[$m['league']['id']] = [
                    'name' => $m['league']['name'],
                    'country' => $c
                ];
            }

            foreach ($availableUpcoming as $row) {
                $c = $row['country'] ?: 'International';
                $availableCountries[$c] = $c;
                $availableLeagues[$row['league_id']] = [
                    'name' => $row['league_name'],
                    'country' => $c
                ];
            }
            ksort($availableCountries);
            uasort($availableLeagues, fn($a, $b) => strcmp($a['name'], $b['name']));

            // 4. Filter live matches for display
            $liveMatches = array_filter($allLiveMatches, function ($m) use ($selectedCountry, $selectedLeague, $selectedBookmaker) {
                $matchesCountry = $selectedCountry === 'all' || ($m['league']['country'] ?? $m['league']['country_name'] ?? '') === $selectedCountry;
                $matchesLeague = $selectedLeague === 'all' || (string) ($m['league']['id'] ?? '') === (string) $selectedLeague;
                $matchesBookie = $selectedBookmaker === 'all' || in_array((int) $selectedBookmaker, $m['available_bookmakers'] ?? []);
                return $matchesCountry && $matchesLeague && $matchesBookie;
            });

            // 5. Fetch upcoming matches if needed, applying display filters
            $upcomingMatches = [];
            if (count($liveMatches) < 10) {
                $sql = "SELECT f.id as fixture_id, f.date, f.status_short, f.league_id,
                               t1.name as home_name, t1.logo as home_logo,
                               t2.name as away_name, t2.logo as away_logo,
                               l.name as league_name, l.country_name as country_name,
                               l.logo as league_logo
                        FROM fixtures f
                        JOIN teams t1 ON f.team_home_id = t1.id
                        JOIN teams t2 ON f.team_away_id = t2.id
                        JOIN leagues l ON f.league_id = l.id
                        WHERE f.date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                        AND f.status_short = 'NS'
                        AND EXISTS (SELECT 1 FROM fixture_odds fo WHERE fo.fixture_id = f.id)";

                if ($selectedCountry !== 'all') {
                    $sql .= " AND l.country_name = " . $db->quote($selectedCountry);
                }
                if ($selectedLeague !== 'all') {
                    $sql .= " AND l.id = " . (int) $selectedLeague;
                }
                if ($selectedBookmaker !== 'all') {
                    $sql .= " AND EXISTS (SELECT 1 FROM fixture_odds fo WHERE fo.fixture_id = f.id AND fo.bookmaker_id = " . (int) $selectedBookmaker . ")";
                }

                $sql .= " ORDER BY f.date ASC LIMIT 20";
                $upcomingMatches = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($upcomingMatches)) {
                    $fids = array_column($upcomingMatches, 'fixture_id');
                    $fidsStr = implode(',', array_map('intval', $fids));
                    $stmt = $db->query("SELECT fixture_id, bookmaker_id FROM fixture_odds WHERE fixture_id IN ($fidsStr)");
                    $bookiesRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $bookiesMap = [];
                    foreach ($bookiesRaw as $row) {
                        $bookiesMap[$row['fixture_id']][] = (int) $row['bookmaker_id'];
                    }
                    foreach ($upcomingMatches as &$m) {
                        $m['available_bookmakers'] = $bookiesMap[$m['fixture_id']] ?? [];
                    }
                }
            }

            // 6. Fetch Hot Predictions with Filters
            $sqlHot = "SELECT p.*, f.date, t1.name as home_name, t1.logo as home_logo, t2.name as away_name, t2.logo as away_logo, l.name as league_name, l.country_name 
                       FROM predictions p 
                       JOIN fixtures f ON p.fixture_id = f.id 
                       JOIN teams t1 ON f.team_home_id = t1.id 
                       JOIN teams t2 ON f.team_away_id = t2.id 
                       JOIN leagues l ON f.league_id = l.id 
                       WHERE f.date > NOW()";

            if ($selectedCountry !== 'all') {
                $sqlHot .= " AND l.country_name = " . $db->quote($selectedCountry);
            }
            if ($selectedLeague !== 'all') {
                $sqlHot .= " AND l.id = " . (int) $selectedLeague;
            }
            if ($selectedBookmaker !== 'all') {
                $sqlHot .= " AND EXISTS (SELECT 1 FROM fixture_odds fo WHERE fo.fixture_id = f.id AND fo.bookmaker_id = " . (int) $selectedBookmaker . ")";
            }

            $sqlHot .= " ORDER BY f.date ASC LIMIT 3";
            $hotPredictions = $db->query($sqlHot)->fetchAll(\PDO::FETCH_ASSOC);

            try {
                // Base query for bets
                try {
                    $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                            FROM bets b
                            LEFT JOIN fixtures f ON b.fixture_id = f.id
                            LEFT JOIN leagues l ON f.league_id = l.id
                            LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                            WHERE 1=1";

                    if ($selectedCountry !== 'all') {
                        $sql .= " AND (l.country_name = " . $db->quote($selectedCountry) . " OR b.country = " . $db->quote($selectedCountry) . ")";
                    }
                    // League filter is tricky for bets, maybe skip or use strict

                    if ($selectedBookmaker !== 'all') {
                        // Se selezioniamo "Betfair", controlliamo betfair_id (se salvato) o bookmaker_id
                        // Assumiamo che il filtro passi un ID numerico
                        $sql .= " AND b.bookmaker_id = " . (int) $selectedBookmaker;
                    }

                    $sql .= " ORDER BY b.timestamp DESC LIMIT 5";
                    $recentActivity = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    // Fallback if bookmaker_id or bookmakers table is missing
                    $sql = "SELECT b.*, l.country_name as country
                            FROM bets b
                            LEFT JOIN fixtures f ON b.fixture_id = f.id
                            LEFT JOIN leagues l ON f.league_id = l.id
                            ORDER BY b.timestamp DESC LIMIT 5"; // Changed limit to 5 for recentActivity
                    $recentActivity = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                    foreach ($recentActivity as &$bet)
                        $bet['bookmaker_name_full'] = $bet['bookmaker_name'] ?? 'Puntata';
                }
            } catch (\Throwable $e) {
                // Fallback if even the simplified query fails
                $recentActivity = $db->query("SELECT b.*, l.name as league_name FROM bets b LEFT JOIN fixtures f ON b.fixture_id = f.id LEFT JOIN leagues l ON f.league_id = l.id ORDER BY b.timestamp DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($recentActivity as &$ra)
                    $ra['bookmaker_name'] = 'Puntata';
            }

            require __DIR__ . '/../Views/partials/dashboard.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewLeagues()
    {
        try {
            $leagues = (new League())->getAll();
            require __DIR__ . '/../Views/partials/leagues.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewLeagueDetails($leagueId)
    {
        try {
            $league = (new League())->getById((int) $leagueId);
            $standings = (new Standing())->getByLeague((int) $leagueId);

            $season = Config::getCurrentSeason();
            $statsModel = new \App\Models\TopStats();
            $topStats = [
                'scorers' => $statsModel->get((int) $leagueId, $season, 'scorers'),
                'assists' => $statsModel->get((int) $leagueId, $season, 'assists')
            ];

            require __DIR__ . '/../Views/partials/league_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewPredictions()
    {
        try {
            $selectedCountry = $_GET['country'] ?? 'all';
            $selectedLeague = $_GET['league'] ?? 'all';

            $db = \App\Services\Database::getInstance()->getConnection();

            // 1. Fetch ALL future predictions to determine filters
            $sqlAll = "SELECT p.advice, p.fixture_id, f.date, f.status_short,
                              t1.id as home_id, t1.name as home_name, t1.logo as home_logo,
                              t2.id as away_id, t2.name as away_name, t2.logo as away_logo,
                              l.id as league_id, l.name as league_name, l.country_name as country_name
                       FROM predictions p
                       JOIN fixtures f ON p.fixture_id = f.id
                       JOIN teams t1 ON f.team_home_id = t1.id
                       JOIN teams t2 ON f.team_away_id = t2.id
                       JOIN leagues l ON f.league_id = l.id
                       WHERE f.date > NOW()
                       ORDER BY f.date ASC";
            $allPredictions = $db->query($sqlAll)->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Aggregate Countries and Leagues for Filters
            $availableCountries = [];
            $availableLeagues = []; // league_id => [name => ..., country => ...]

            foreach ($allPredictions as $p) {
                $c = $p['country_name'] ?: 'International';
                $availableCountries[$c] = $c;
                $availableLeagues[$p['league_id']] = [
                    'name' => $p['league_name'],
                    'country' => $c
                ];
            }
            ksort($availableCountries);
            uasort($availableLeagues, fn($a, $b) => strcmp($a['name'], $b['name']));

            // 3. Filter predictions based on selection
            $predictions = array_filter($allPredictions, function ($p) use ($selectedCountry, $selectedLeague) {
                $matchesCountry = $selectedCountry === 'all' || ($p['country_name'] ?: 'International') === $selectedCountry;
                $matchesLeague = $selectedLeague === 'all' || (string) $p['league_id'] === (string) $selectedLeague;
                return $matchesCountry && $matchesLeague;
            });

            require __DIR__ . '/../Views/partials/predictions.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewTeam($teamId)
    {
        try {
            $team = (new Team())->getById((int) $teamId);
            $coach = (new Coach())->getByTeam((int) $teamId);
            $squad = (new Player())->getByTeam((int) $teamId);
            $db = \App\Services\Database::getInstance()->getConnection();
            $latest = $db->query("SELECT league_id, season FROM team_stats WHERE team_id = " . (int) $teamId . " ORDER BY season DESC LIMIT 1")->fetch();
            $stats = $latest ? (new TeamStats())->get((int) $teamId, (int) $latest['league_id'], (int) $latest['season']) : null;
            if (!$team) {
                echo '<div class="glass p-20 text-center italic">Squadra non trovata.</div>';
                return;
            }
            require __DIR__ . '/../Views/partials/team.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewPlayer($playerId)
    {
        try {
            $player = (new Player())->getById((int) $playerId);
            $stats = (new \App\Models\PlayerStatistics())->get((int) $playerId, Config::getCurrentSeason());
            $trophies = (new \App\Models\Trophy())->getByPlayer((int) $playerId);
            $transfers = (new \App\Models\Transfer())->getByPlayer((int) $playerId);
            if (!$player) {
                echo '<div class="glass p-20 text-center italic">Giocatore non trovato.</div>';
                return;
            }
            require __DIR__ . '/../Views/partials/player.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewMatchTab($id, $tab)
    {
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $id = (int) $id;

            switch ($tab) {
                case 'analysis':
                    $data = (new Prediction())->getByFixtureId($id);
                    require __DIR__ . '/../Views/partials/match/analysis.php';
                    break;

                case 'events':
                    $stmt = $db->prepare("SELECT * FROM fixtures WHERE id = ?");
                    $stmt->execute([$id]);
                    $fix = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $homeId = $fix['team_home_id'];
                    $events = (new FixtureEvent())->getByFixture($id);
                    require __DIR__ . '/../Views/partials/match/events.php';
                    break;

                case 'lineups':
                    $lineups = (new FixtureLineup())->getByFixture($id);
                    require __DIR__ . '/../Views/partials/match/lineups.php';
                    break;

                case 'stats':
                    $statistics = (new FixtureStatistics())->getByFixture($id);
                    require __DIR__ . '/../Views/partials/match/stats.php';
                    break;

                case 'h2h':
                    $stmt = $db->prepare("SELECT team_home_id, team_away_id FROM fixtures WHERE id = ?");
                    $stmt->execute([$id]);
                    $fix = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $h2hData = (new H2H())->get($fix['team_home_id'], $fix['team_away_id']);
                    // H2H model already decodes h2h_json
                    $h2h = $h2hData['h2h_json'] ?? [];
                    require __DIR__ . '/../Views/partials/match/h2h.php';
                    break;

                case 'odds':
                    $odds = (new FixtureOdds())->getByFixture($id);
                    require __DIR__ . '/../Views/partials/match/odds.php';
                    break;

                case 'info':
                    $fixture = (new Fixture())->getById($id);
                    require __DIR__ . '/../Views/partials/match/info.php';
                    break;

                default:
                    echo "Tab non valida.";
            }
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore caricamento tab: ' . $e->getMessage() . '</div>';
        }
    }

    public function viewMatch($id)
    {
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $fixture = $db->query("SELECT f.*, t1.name as team_home_name, t1.logo as team_home_logo, t2.name as team_away_name, t2.logo as team_away_logo, l.name as league_name, l.logo as league_logo FROM fixtures f JOIN teams t1 ON f.team_home_id = t1.id JOIN teams t2 ON f.team_away_id = t2.id JOIN leagues l ON f.league_id = l.id WHERE f.id = " . (int) $id)->fetch(\PDO::FETCH_ASSOC);
            if (!$fixture) {
                echo '<div class="glass p-20 text-center italic">Match non trovato.</div>';
                return;
            }

            $matchData = [
                'fixture' => $fixture,
                'events' => (new FixtureEvent())->getByFixture((int) $id),
                'lineups' => (new FixtureLineup())->getByFixture((int) $id),
                'statistics' => (new FixtureStatistics())->getByFixture((int) $id),
                'odds' => (new FixtureOdds())->getByFixture((int) $id),
                'injuries' => (new FixtureInjury())->getByFixture((int) $id),
                'h2h' => (new H2H())->get($fixture['team_home_id'], $fixture['team_away_id'])
            ];
            require __DIR__ . '/../Views/partials/match.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function getPredictions($limit = 10)
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::DATA_PATH . 'betfair_hot_predictions.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                echo json_encode(['response' => array_slice($data['response'] ?? [], 0, $limit)]);
            } else {
                // Fallback a upcoming se non c'è ancora l'analisi
                $upcomingFile = Config::DATA_PATH . 'betfair_upcoming.json';
                if (file_exists($upcomingFile)) {
                    $data = json_decode(file_get_contents($upcomingFile), true);
                    echo json_encode(['response' => array_slice($data['response'] ?? [], 0, $limit)]);
                } else {
                    echo json_encode(['response' => []]);
                }
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getStandings($leagueId)
    {
        header('Content-Type: application/json');
        try {
            $data = (new Standing())->getByLeague((int) $leagueId);
            echo json_encode(['response' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getLeagueStats($leagueId)
    {
        header('Content-Type: application/json');
        try {
            $season = Config::getCurrentSeason();
            $topStats = new \App\Models\TopStats();
            $data = [
                'scorers' => $topStats->get((int) $leagueId, $season, 'scorers'),
                'assists' => $topStats->get((int) $leagueId, $season, 'assists')
            ];
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getTeamDetails($teamId)
    {
        header('Content-Type: application/json');
        try {
            $team = (new Team())->getById((int) $teamId);
            $coach = (new Coach())->getByTeam((int) $teamId);
            $squad = (new Player())->getByTeam((int) $teamId);
            $db = \App\Services\Database::getInstance()->getConnection();
            $latest = $db->query("SELECT league_id, season FROM team_stats WHERE team_id = " . (int) $teamId . " ORDER BY season DESC LIMIT 1")->fetch();
            $stats = $latest ? (new TeamStats())->get((int) $teamId, (int) $latest['league_id'], (int) $latest['season']) : null;
            echo json_encode(['team' => $team, 'coach' => $coach, 'squad' => $squad, 'stats' => $stats]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getPlayerDetails($playerId)
    {
        header('Content-Type: application/json');
        try {
            $player = (new Player())->getById((int) $playerId);
            $stats = (new \App\Models\PlayerStatistics())->get((int) $playerId, Config::getCurrentSeason());
            $trophies = (new \App\Models\Trophy())->getByPlayer((int) $playerId);
            $transfers = (new \App\Models\Transfer())->getByPlayer((int) $playerId);
            echo json_encode(['player' => $player, 'statistics' => $stats, 'trophies' => $trophies, 'transfers' => $transfers]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getLeagues()
    {
        header('Content-Type: application/json');
        try {
            $data = (new League())->getAll();
            echo json_encode(['response' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getPredictionsAll()
    {
        return $this->getPredictions(100);
    }
}

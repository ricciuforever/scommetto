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
use App\Models\Prediction as PredictionModel;
use App\Models\Analysis as AnalysisModel;

class MatchController
{
    private $geminiService;
    private $betSettler;

    public function __construct()
    {
        // Strictly no FootballApiService here anymore
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

    /**
     * Reads live matches from local cache only.
     * The Cron job is responsible for updating this file.
     */
    public function getLive()
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::LIVE_DATA_FILE;
            if (file_exists($cacheFile)) {
                // Return cache directly as it's already filtered and enriched by syncLive
                echo file_get_contents($cacheFile);
            } else {
                echo json_encode(['response' => [], 'status' => 'waiting_for_sync']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getUpcoming()
    {
        header('Content-Type: application/json');
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $sql = "SELECT f.id as fixture_id, f.date, f.status_short, f.league_id,
                           t1.name as home_name, t1.logo as home_logo,
                           t2.name as away_name, t2.logo as away_logo,
                           l.name as league_name, l.country_name as country_name, l.flag as country_flag
                    FROM fixtures f
                    JOIN teams t1 ON f.team_home_id = t1.id
                    JOIN teams t2 ON f.team_away_id = t2.id
                    JOIN leagues l ON f.league_id = l.id
                    WHERE f.date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    AND f.status_short = 'NS'
                    AND EXISTS (SELECT 1 FROM fixture_odds fo WHERE fo.fixture_id = f.id)
                    ORDER BY f.date ASC LIMIT 20";
            $data = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($data)) {
                $fids = array_column($data, 'fixture_id');
                $fids = array_filter($fids); // Extra safety
                if (!empty($fids)) {
                    $fidsStr = implode(',', array_map('intval', $fids));
                    $stmt = $db->query("SELECT fixture_id, bookmaker_id FROM fixture_odds WHERE fixture_id IN ($fidsStr)");
                    $oddsRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $bookmakersByFixture = [];
                    foreach ($oddsRaw as $row) {
                        $bookmakersByFixture[$row['fixture_id']][] = (int) $row['bookmaker_id'];
                    }

                    foreach ($data as &$m) {
                        $fid = $m['fixture_id'];
                        $m['available_bookmakers'] = $bookmakersByFixture[$fid] ?? [];
                    }
                } else {
                    foreach ($data as &$m) {
                        $m['available_bookmakers'] = [];
                    }
                }
            }

            echo json_encode(['response' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getLeagueStats($leagueId)
    {
        header('Content-Type: application/json');
        try {
            $topStatsModel = new \App\Models\TopStats();
            $season = Config::getCurrentSeason();
            $types = ['scorers', 'assists', 'yellow_cards', 'red_cards'];
            $results = [];
            foreach ($types as $type) {
                $results[$type] = $topStatsModel->get((int) $leagueId, $season, $type);
            }
            echo json_encode($results);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getMatch($id)
    {
        header('Content-Type: application/json');
        try {
            $db = \App\Services\Database::getInstance()->getConnection();

            // Basic Info
            $fixtureModel = new \App\Models\Fixture();
            $fixture = $fixtureModel->getById((int) $id);

            // FALLBACK: If not in DB, try to fetch from API immediately (Real-time on-demand)
            if (!$fixture) {
                $apiService = new \App\Services\FootballApiService();
                $data = $apiService->request("/fixtures?id=$id");

                if (isset($data['response'][0])) {
                    $fixtureData = $data['response'][0];
                    $fixtureModel->save($fixtureData);

                    // Also save related teams, league, venue if missing? 
                    // Ideally yes, but let's stick to fixture first to be fast.
                    // Actually, save() method of Fixture model relies on 'teams', 'league' keys in data.
                    // The 'save' method in Fixture model is robust enough to handle the structure.

                    // Reload from DB to ensure we have the localized fields and structure
                    $fixture = $fixtureModel->getById((int) $id);
                }
            }

            if (!$fixture) {
                echo json_encode(['error' => 'Partita non trovata (né DB né API).']);
                return;
            }

            // Events
            $events = (new \App\Models\FixtureEvent())->getByFixture((int) $id);

            // Stats
            $stats = (new \App\Models\FixtureStatistics())->getByFixture((int) $id);

            // Lineups
            $lineups = (new \App\Models\FixtureLineup())->getByFixture((int) $id);

            // Injuries
            $injuries = (new \App\Models\FixtureInjury())->getByFixture((int) $id);

            // H2H
            $h2h = (new \App\Models\H2H())->get($fixture['team_home_id'], $fixture['team_away_id']);

            // Odds
            $odds = (new \App\Models\FixtureOdds())->getByFixture((int) $id);

            echo json_encode([
                'fixture' => $fixture,
                'events' => $events,
                'statistics' => $stats,
                'lineups' => $lineups,
                'injuries' => $injuries,
                'h2h' => $h2h,
                'odds' => $odds
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Performs AI analysis using strictly local DB data.
     */
    public function analyze($id)
    {
        header('Content-Type: application/json');
        try {
            $cacheFile = Config::LIVE_DATA_FILE;
            $liveData = json_decode(file_exists($cacheFile) ? file_get_contents($cacheFile) : '{"response":[]}', true);
            $match = null;

            foreach ($liveData['response'] ?? [] as $m) {
                if ($m['fixture']['id'] == $id) {
                    $match = $m;
                    break;
                }
            }

            if (!$match) {
                // FALLBACK: Fetch from DB for Upcoming/Predicted matches
                $db = \App\Services\Database::getInstance()->getConnection();

                // Get basic fixture info
                $sql = "SELECT f.id, f.date, f.status_short, f.status_long, f.status_elapsed, f.league_id,
                               t1.name as home_name, t1.logo as home_logo, t1.id as home_id,
                               t2.name as away_name, t2.logo as away_logo, t2.id as away_id,
                               l.name as league_name, l.country_name, l.logo as league_logo, l.flag as country_flag
                        FROM fixtures f
                        JOIN teams t1 ON f.team_home_id = t1.id
                        JOIN teams t2 ON f.team_away_id = t2.id
                        JOIN leagues l ON f.league_id = l.id
                        WHERE f.id = :id";

                $stmt = $db->prepare($sql);
                $stmt->execute(['id' => $id]);
                $fix = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($fix) {
                    // Construct a match object compatible with frontend/gemini
                    $match = [
                        'fixture' => [
                            'id' => $fix['id'],
                            'date' => $fix['date'],
                            'status' => [
                                'short' => $fix['status_short'],
                                'long' => $fix['status_long'],
                                'elapsed' => $fix['status_elapsed']
                            ]
                        ],
                        'league' => [
                            'id' => $fix['league_id'],
                            'name' => $fix['league_name'],
                            'country' => $fix['country_name'],
                            'logo' => $fix['league_logo'],
                            'flag' => $fix['country_flag']
                        ],
                        'teams' => [
                            'home' => ['id' => $fix['home_id'], 'name' => $fix['home_name'], 'logo' => $fix['home_logo']],
                            'away' => ['id' => $fix['away_id'], 'name' => $fix['away_name'], 'logo' => $fix['away_logo']]
                        ],
                        'goals' => ['home' => null, 'away' => null], // Not live, so no guaranteed score
                        'score' => ['halftime' => [], 'fulltime' => [], 'extratime' => [], 'penalty' => []]
                    ];

                    // Try to get score/events/stats from DB if available
                    $eventModel = new \App\Models\FixtureEvent();
                    $match['events'] = $eventModel->getByFixture($id);

                    $statModel = new \App\Models\FixtureStatistics();
                    $stats = $statModel->getByFixture($id);
                    $match['statistics'] = [];
                    foreach ($stats as $s) {
                        $match['statistics'][] = [
                            'team' => ['id' => $s['team_id'], 'name' => ($s['team_id'] == $fix['home_id'] ? $fix['home_name'] : $fix['away_name']), 'logo' => ($s['team_id'] == $fix['home_id'] ? $fix['home_logo'] : $fix['away_logo'])],
                            'statistics' => json_decode($s['statistics'], true)
                        ];
                    }

                    $lineupModel = new \App\Models\FixtureLineup();
                    $lineups = $lineupModel->getByFixture($id);
                    $match['lineups'] = [];
                    foreach ($lineups as $l) {
                        $match['lineups'][] = [
                            'team' => ['id' => $l['team_id'], 'name' => ($l['team_id'] == $fix['home_id'] ? $fix['home_name'] : $fix['away_name'])],
                            'startXI' => json_decode($l['start_xi_json'] ?? '[]', true),
                            'substitutes' => json_decode($l['subs_json'] ?? '[]', true),
                            'formation' => $l['formation'] ?? ''
                        ];
                    }

                    // We also need goals because frontend uses match.goals
                    $match['goals'] = [
                        'home' => $fix['score_home'] ?? 0,
                        'away' => $fix['score_away'] ?? 0
                    ];

                } else {
                    echo json_encode(['error' => 'Partita non trovata nel database.']);
                    return;
                }
            }

            // Gemini analysis uses IntelligenceService which is already DB-only
            $prediction = $this->geminiService->analyze($match);

            try {
                $analysisModel = new Analysis();
                $analysisModel->log((int) $id, $prediction);
            } catch (\Throwable $e) {
                error_log("Error saving analysis: " . $e->getMessage());
            }

            echo json_encode([
                'fixture_id' => $id,
                'prediction' => $prediction,
                'match' => $match
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets predictions from DB only.
     */
    public function getPredictions($id)
    {
        header('Content-Type: application/json');
        try {
            $predictionModel = new Prediction();
            $data = $predictionModel->getByFixtureId((int) $id);

            if (!$data) {
                echo json_encode(['error' => 'Pronostico non ancora disponibile nel database.']);
                return;
            }
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets standings from DB only.
     */
    public function getStandings($leagueId)
    {
        header('Content-Type: application/json');
        try {
            $standingModel = new Standing();
            $data = $standingModel->getByLeague((int) $leagueId);

            if (empty($data)) {
                echo json_encode(['error' => 'Classifica non ancora sincronizzata nel database.']);
                return;
            }
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets team, coach and squad from DB only.
     */
    public function getTeamDetails($teamId)
    {
        header('Content-Type: application/json');
        try {
            $teamModel = new Team();
            $coachModel = new Coach();
            $playerModel = new Player();
            $statsModel = new \App\Models\TeamStats();

            $team = $teamModel->getById((int) $teamId);
            $coach = $coachModel->getByTeam((int) $teamId);
            $squad = $playerModel->getByTeam((int) $teamId);

            // Get stats for the most recent league/season
            $db = \App\Services\Database::getInstance()->getConnection();
            $latest = $db->query("SELECT league_id, season FROM team_stats WHERE team_id = " . (int) $teamId . " ORDER BY season DESC LIMIT 1")->fetch();
            $stats = null;
            if ($latest) {
                $stats = $statsModel->get((int) $teamId, (int) $latest['league_id'], (int) $latest['season']);
            }

            if (!$team) {
                echo json_encode(['error' => 'Dati squadra non presenti nel database. Attendi il cron sync.']);
                return;
            }

            echo json_encode([
                'team' => $team,
                'coach' => $coach,
                'squad' => $squad,
                'statistics' => $stats
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Gets player details from DB only.
     */
    public function getPlayerDetails($playerId)
    {
        header('Content-Type: application/json');
        try {
            $playerModel = new Player();
            $statsModel = new \App\Models\PlayerStatistics();
            $trophyModel = new \App\Models\Trophy();
            $transferModel = new \App\Models\Transfer();

            $player = $playerModel->getById((int) $playerId);
            $season = Config::getCurrentSeason();
            $stats = $statsModel->get((int) $playerId, $season);
            $trophies = $trophyModel->getByPlayer((int) $playerId);
            $transfers = $transferModel->getByPlayer((int) $playerId);

            if (!$player) {
                echo json_encode(['error' => 'Dettagli giocatore non presenti nel database.']);
                return;
            }

            echo json_encode([
                'player' => $player,
                'statistics' => $stats,
                'trophies' => $trophies,
                'transfers' => $transfers
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getLeagues()
    {
        header('Content-Type: application/json');
        try {
            $leagueModel = new League();
            echo json_encode($leagueModel->getAll());
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getPredictionsAll()
    {
        header('Content-Type: application/json');
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $sql = "SELECT p.*, f.date, f.status_short, f.league_id,
                           t1.name as home_name, t1.logo as home_logo,
                           t2.name as away_name, t2.logo as away_logo,
                           l.name as league_name, l.country_name as country_name
                    FROM predictions p
                    JOIN fixtures f ON p.fixture_id = f.id
                    JOIN teams t1 ON f.team_home_id = t1.id
                    JOIN teams t2 ON f.team_away_id = t2.id
                    JOIN leagues l ON f.league_id = l.id
                    WHERE f.date >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
                    AND f.status_short NOT IN ('FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD', 'AWD', 'WO')
                    ORDER BY f.date ASC";
            $data = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Serves the dashboard partial for HTMX
     */
    public function dashboard()
    {
        try {
            // Get filter params
            $country = $_GET['country'] ?? 'all';
            $bookmaker = $_GET['bookmaker'] ?? 'all';
            $pinned = isset($_GET['pinned']) ? explode(',', $_GET['pinned']) : [];

            $cacheFile = Config::LIVE_DATA_FILE;
            $liveMatches = [];

            if (file_exists($cacheFile)) {
                $raw = json_decode(file_get_contents($cacheFile), true);
                if (isset($raw['response']) && is_array($raw['response'])) {
                    $liveMatches = $raw['response'];

                    // Simple filtering
                    if ($country !== 'all') {
                        $liveMatches = array_filter($liveMatches, function ($m) use ($country) {
                            return isset($m['league']['country']) && $m['league']['country'] === $country;
                        });
                    }

                    // Filter by bookmaker available if needed (logic from JS)
                    if ($bookmaker !== 'all') {
                        // This logic is complex because 'available_bookmakers' might not be in the live feed directly
                        // unless we enhance the sync script. For now, we trust the frontend or ignore.
                    }
                }
            }

            // Pass variables to view
            $pinnedMatches = $pinned;
            require __DIR__ . '/../Views/partials/dashboard.php';

        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore caricamento dashboard: ' . $e->getMessage() . '</div>';
        }
    }

    /**
     * Serves the leagues partial for HTMX
     */
    public function viewLeagues()
    {
        try {
            // Fetch leagues using the model
            $leagueModel = new League();
            $leagues = $leagueModel->getAll(); // Assume this returns an array of leagues

            // Pass variables to view
            require __DIR__ . '/../Views/partials/leagues.php';

        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore caricamento competizioni: ' . $e->getMessage() . '</div>';
        }
    }
}

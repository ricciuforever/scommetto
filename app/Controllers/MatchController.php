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
                $data = json_decode(file_get_contents($cacheFile), true);

                if (isset($data['response']) && !empty($data['response'])) {
                    $db = \App\Services\Database::getInstance()->getConnection();
                    $fids = [];
                    foreach ($data['response'] as $m)
                        $fids[] = $m['fixture']['id'];

                    if (!empty($fids)) {
                        $fidsStr = implode(',', $fids);
                        // Get bookmakers from fixture_odds
                        $stmt = $db->query("SELECT fixture_id, bookmaker_id FROM fixture_odds WHERE fixture_id IN ($fidsStr)");
                        $oddsRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        $bookmakersByFixture = [];
                        foreach ($oddsRaw as $row) {
                            $bookmakersByFixture[$row['fixture_id']][] = (int) $row['bookmaker_id'];
                        }

                        foreach ($data['response'] as &$m) {
                            $fid = $m['fixture']['id'];
                            $m['available_bookmakers'] = $bookmakersByFixture[$fid] ?? [];
                        }
                    }
                }
                echo json_encode($data);
            } else {
                echo json_encode(['response' => [], 'status' => 'waiting_for_sync']);
            }
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
            $fixture = (new \App\Models\Fixture())->getById((int) $id);
            if (!$fixture) {
                echo json_encode(['error' => 'Partita non trovata.']);
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
                echo json_encode(['error' => 'Partita non trovata nei dati live locali. Attendi sincronizzazione.']);
                return;
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
                    WHERE f.date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY f.date ASC";
            $data = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($data);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

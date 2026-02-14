<?php
// index.php

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\MatchController;
use App\Controllers\BetController;
use App\Controllers\SyncController;
use App\Controllers\FilterController;
use App\Controllers\CountryController;
use App\Controllers\LeagueController;
use App\Controllers\SeasonController;
use App\Controllers\TeamController;
use App\Controllers\PlayerController;
use App\Controllers\PlayerSeasonStatsController;
use App\Controllers\RoundController;
use App\Controllers\FixtureController;
use App\Controllers\FixtureStatsController;
use App\Controllers\FixtureEventController;
use App\Controllers\FixtureLineupController;
use App\Controllers\FixturePlayerStatsController;
use App\Controllers\FixtureInjuryController;
use App\Controllers\FixturePredictionController;
use App\Controllers\H2HController;
use App\Controllers\TeamStatsController;
use App\Controllers\VenueController;
use App\Controllers\StandingController;
use App\Controllers\OddsController;

// Basic Routing & Path Normalization
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Strip subdirectory if present (e.g., /scommetto/)
$path = preg_replace('#^/scommetto#i', '', $path);
// Ensure path starts with / and has no trailing slash (unless it's just /)
$path = '/' . trim($path, '/');
if ($path === '//')
    $path = '/';

// Debug Log
$logMsg = date('[Y-m-d H:i:s] ') . $method . " " . $requestUri . " -> Normalized: " . $path . PHP_EOL;
file_put_contents(__DIR__ . '/logs/router_debug.log', $logMsg, FILE_APPEND);

// Serve static files from public directory
if (preg_match('#\.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$#i', $path)) {
    $filePath = __DIR__ . '/public' . $path;
    if (file_exists($filePath)) {
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        exit;
    }
}

try {
    // Gestione viste standard tramite Controller dedicati
    $viewRoutes = [
        '/countries' => CountryController::class,
        '/leagues' => LeagueController::class,
        '/seasons' => SeasonController::class,
        '/teams' => TeamController::class,
        '/players' => PlayerController::class,
        '/rounds' => RoundController::class,
        '/fixtures' => FixtureController::class,
        '/fixture-stats' => FixtureStatsController::class,
        '/fixture-events' => FixtureEventController::class,
        '/fixture-lineups' => FixtureLineupController::class,
        '/fixture-player-stats' => FixturePlayerStatsController::class,
        '/fixture-injuries' => FixtureInjuryController::class,
        '/fixture-predictions' => FixturePredictionController::class,
        '/h2h' => H2HController::class,
        '/team-stats' => TeamStatsController::class,
        '/venues' => VenueController::class,
        '/standings' => StandingController::class,
    ];

    // --- GIANIK ROUTES ---
    if ($path === '/gianik-live' || $path === '/gianik') {
        (new \App\GiaNik\Controllers\GiaNikController())->index();
        return;
    }

    if ($path === '/gianik/brain') {
        (new \App\GiaNik\Controllers\GiaNikController())->brain();
        return;
    }

    // --- DIO (QUANTUM) ROUTES ---
    if ($path === '/dio') {
        (new \App\Dio\Controllers\DioQuantumController())->index();
        return;
    }

    if ($path === '/api/dio/scan') {
        (new \App\Dio\Controllers\DioQuantumController())->scanAndTrade();
        return;
    }

    if ($path === '/api/gianik/live') {
        (new \App\GiaNik\Controllers\GiaNikController())->live();
        return;
    }

    if ($path === '/api/gianik/analyze') {
        $mId = $_GET['marketId'] ?? null;
        if ($mId) {
            (new \App\GiaNik\Controllers\GiaNikController())->analyze($mId);
            return;
        }
    }


    if (preg_match('#^/api/gianik/analyze/([\d\.]+)$#i', $path, $matches)) {
        (new \App\GiaNik\Controllers\GiaNikController())->analyze($matches[1]);
        return;
    }

    if ($path === '/api/gianik/place-bet') {
        (new \App\GiaNik\Controllers\GiaNikController())->placeBet();
        return;
    }

    if ($path === '/api/gianik/auto-process') {
        (new \App\GiaNik\Controllers\GiaNikController())->autoProcess();
        return;
    }

    if ($path === '/api/gianik/learn') {
        (new \App\GiaNik\Controllers\GiaNikController())->learn();
        return;
    }

    if ($path === '/api/gianik/brain-rebuild') {
        (new \App\GiaNik\Controllers\BrainController())->rebuild();
        return;
    }

    if ($path === '/api/gianik/recent-bets') {
        (new \App\GiaNik\Controllers\GiaNikController())->recentBets();
        return;
    }

    if (preg_match('#^/api/gianik/bet/(\d+)$#i', $path, $matches)) {
        (new \App\GiaNik\Controllers\GiaNikController())->betDetails($matches[1]);
        return;
    }

    if ($path === '/api/gianik/set-mode') {
        (new \App\GiaNik\Controllers\GiaNikController())->setMode();
        return;
    }

    if ($path === '/api/gianik/get-mode') {
        (new \App\GiaNik\Controllers\GiaNikController())->getMode();
        return;
    }

    if ($path === '/api/gianik/team-details') {
        $tId = $_GET['teamId'] ?? null;
        if ($tId) {
            (new \App\GiaNik\Controllers\GiaNikController())->teamDetails($tId);
            return;
        }
    }

    if ($path === '/api/gianik/player-details') {
        $pId = $_GET['playerId'] ?? null;
        $fId = $_GET['fixtureId'] ?? null;
        if ($pId) {
            (new \App\GiaNik\Controllers\GiaNikController())->playerDetails($pId, $fId);
            return;
        }
    }

    // NEW: Player Modal (HTML)
    if (preg_match('#^/gianik/player-modal/(\d+)$#i', $path, $matches)) {
        $playerId = $matches[1];
        $fixtureId = $_GET['fixture'] ?? null;
        (new \App\GiaNik\Controllers\GiaNikController())->playerDetails($playerId, $fixtureId);
        return;
    }

    // NEW: Team Modal (HTML)
    if (preg_match('#^/gianik/team-modal/(\d+)$#i', $path, $matches)) {
        $teamId = $matches[1];
        (new \App\GiaNik\Controllers\GiaNikController())->teamDetails($teamId);
        return;
    }

    // NEW: API endpoint for complete match data
    if (preg_match('#^/api/match-details/(\d+)$#i', $path, $matches)) {
        (new \App\GiaNik\Controllers\MatchDataController())->getMatchDetails($matches[1]);
        return;
    }

    // NEW: API endpoint for player data
    if (preg_match('#^/api/player-details/(\d+)$#i', $path, $matches)) {
        (new \App\GiaNik\Controllers\MatchDataController())->getPlayerDetails($matches[1]);
        return;
    }

    // NEW: API endpoint for team data
    if (preg_match('#^/api/team-details/(\d+)$#i', $path, $matches)) {
        (new \App\GiaNik\Controllers\MatchDataController())->getTeamDetails($matches[1]);
        return;
    }

    // --- MAIN APP ROUTES ---
    if (isset($viewRoutes[$path])) {
        (new $viewRoutes[$path]())->index();
        return;
    }

    if ($path === '/gemini-bets') {
        (new \App\Controllers\BetController())->index();
        return;
    }

    if ($path === '/gemini-predictions') {
        (new \App\Controllers\MatchController())->viewUpcomingPredictions();
        return;
    }

    // Dio Quantum as Home
    if ($path === '/' || $path === '/dio') {
        (new \App\Dio\Controllers\DioQuantumController())->index();
        return;
    }

    if ($path === '/intelligence') {
        (new \App\Controllers\IntelligenceController())->index();
        return;
    }

    // Old Dashboard and other routes via MatchController (main.php wrapper)
    if (
        in_array($path, ['/leagues', '/settings']) ||
        preg_match('#^/(match|team|player)/(\d+)$#i', $path)
    ) {
        (new MatchController())->index();
        return;
    } elseif ($path === '/api/intelligence/live') {
        (new \App\Controllers\IntelligenceController())->live();
    } elseif ($path === '/api/live') {
        (new MatchController())->getLive();
    } elseif ($path === '/api/countries') {
        (new CountryController())->list();
    } elseif ($path === '/api/teams/countries') {
        (new CountryController())->listTeams();
    } elseif ($path === '/api/leagues') {
        (new LeagueController())->list();
    } elseif ($path === '/api/seasons') {
        (new SeasonController())->list();
    } elseif ($path === '/api/rounds') {
        (new RoundController())->list();
    } elseif ($path === '/api/fixtures') {
        (new FixtureController())->list();
    } elseif ($path === '/api/fixture-stats') {
        (new FixtureStatsController())->show();
    } elseif ($path === '/api/fixture-events') {
        (new FixtureEventController())->show();
    } elseif ($path === '/api/fixture-lineups') {
        (new FixtureLineupController())->show();
    } elseif ($path === '/api/fixture-player-stats') {
        (new FixturePlayerStatsController())->show();
    } elseif ($path === '/api/fixture-injuries') {
        (new FixtureInjuryController())->show();
    } elseif ($path === '/api/fixture-predictions') {
        (new FixturePredictionController())->show();
    } elseif (preg_match('#^/api/fixtures/(\d+)/statistics$#', $path, $matches)) {
        $_GET['fixture'] = $matches[1];
        (new FixtureStatsController())->show();
    } elseif (preg_match('#^/api/fixtures/(\d+)/events$#', $path, $matches)) {
        $_GET['fixture'] = $matches[1];
        (new FixtureEventController())->show();
    } elseif (preg_match('#^/api/fixtures/(\d+)/lineups$#', $path, $matches)) {
        $_GET['fixture'] = $matches[1];
        (new FixtureLineupController())->show();
    } elseif (preg_match('#^/api/fixtures/(\d+)/h2h$#', $path, $matches)) {
        $fixtureId = $matches[1];
        $fixtureModel = new \App\Models\Fixture();
        $fixture = $fixtureModel->getById($fixtureId);
        if ($fixture) {
            $_GET['t1'] = $fixture['team_home_id'];
            $_GET['t2'] = $fixture['team_away_id'];
            (new H2HController())->show();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['response' => [], 'error' => 'Fixture not found']);
        }
    } elseif ($path === '/api/h2h') {
        (new H2HController())->show();
    } elseif ($path === '/api/teams') {
        (new TeamController())->list();
    } elseif ($path === '/api/players') {
        (new PlayerController())->show();
    } elseif ($path === '/api/player-season-stats') {
        (new PlayerSeasonStatsController())->show();
    } elseif ($path === '/api/player-teams') {
        (new PlayerController())->teams();
    } elseif ($path === '/api/player-transfers') {
        (new PlayerController())->transfers();
    } elseif ($path === '/api/player-trophies') {
        (new PlayerController())->trophies();
    } elseif ($path === '/api/player-sidelined') {
        (new PlayerController())->sidelined();
    } elseif ($path === '/api/team/seasons') {
        (new TeamController())->seasons();
    } elseif ($path === '/api/squads') {
        (new TeamController())->squads();
    } elseif ($path === '/api/odds/live') {
        (new OddsController())->live();
    } elseif ($path === '/api/odds/live/bets') {
        (new OddsController())->liveBets();
    } elseif ($path === '/api/odds/active-bookmakers') {
        (new OddsController())->activeBookmakers();
    } elseif ($path === '/api/odds') {
        (new OddsController())->prematch();
    } elseif ($path === '/api/odds/bookmakers') {
        (new OddsController())->bookmakers();
    } elseif ($path === '/api/odds/bets') {
        (new OddsController())->bets();
    } elseif ($path === '/api/league-top-stats') {
        (new LeagueController())->topStats();
    } elseif ($path === '/api/team-stats') {
        (new TeamStatsController())->show();
    } elseif ($path === '/api/standings') {
        (new StandingController())->show();
    } elseif ($path === '/api/venues') {
        (new VenueController())->list();
    } elseif ($path === '/api/dashboard' || $path === '/api/view/dashboard') {
        (new MatchController())->dashboard();
    } elseif ($path === '/api/view/leagues') {
        (new MatchController())->viewLeagues();
    } elseif (preg_match('#^/api/view/leagues/(\d+)$#', $path, $matches)) {
        (new MatchController())->viewLeagueDetails($matches[1]);
    } elseif (preg_match('#^/api/view/match/(\d+)/tab/(\w+)$#', $path, $matches)) {
        (new MatchController())->viewMatchTab($matches[1], $matches[2]);
    } elseif (preg_match('#^/api/view/match/(\d+)$#', $path, $matches)) {
        (new MatchController())->viewMatch($matches[1]);
    } elseif (preg_match('#^/api/view/team/(\d+)$#', $path, $matches)) {
        (new MatchController())->viewTeam($matches[1]);
    } elseif (preg_match('#^/api/view/player/(\d+)$#', $path, $matches)) {
        (new MatchController())->viewPlayer($matches[1]);
    } elseif ($path === '/api/upcoming') {
        (new MatchController())->getUpcoming();
    } elseif ($path === '/api/history') {
        (new BetController())->getHistory();
    } elseif (preg_match('#^/api/match/(\d+)$#i', $path, $matches)) {
        (new MatchController())->viewMatch($matches[1]);
    } elseif (preg_match('#^/api/analyze/(\d+)$#i', $path, $matches)) {
        (new MatchController())->analyze($matches[1]);
    } elseif (preg_match('#^/api/predictions/(\d+)$#i', $path, $matches)) {
        (new MatchController())->getPredictions($matches[1]);
    } elseif (preg_match('#^/api/standings/(\d+)$#', $path, $matches)) {
        (new MatchController())->getStandings($matches[1]);
    } elseif (preg_match('#^/api/leagues/stats/(\d+)$#', $path, $matches)) {
        (new MatchController())->getLeagueStats($matches[1]);
    } elseif (preg_match('#^/api/team/(\d+)$#', $path, $matches)) {
        (new MatchController())->getTeamDetails($matches[1]);
    } elseif (preg_match('#^/api/player/(\d+)$#', $path, $matches)) {
        (new MatchController())->getPlayerDetails($matches[1]);
    } elseif ($path === '/api/leagues') {
        (new MatchController())->getLeagues();
    } elseif ($path === '/api/predictions/all') {
        (new MatchController())->getPredictionsAll();
    } elseif ($path === '/api/place_bet' && $method === 'POST') {
        (new BetController())->placeBet();
    } elseif ($path === '/api/sync') {
        (new SyncController())->sync();
    } elseif ($path === '/api/deep-sync') {
        $league = $_GET['league'] ?? 135;

        // Calcolo dinamico tramite Config
        $defaultSeason = \App\Config\Config::getCurrentSeason();

        // Prende la stagione dall'URL se presente (?season=2025), altrimenti usa quella dinamica
        $season = isset($_GET['season']) ? (int) $_GET['season'] : $defaultSeason;

        (new SyncController())->deepSync($league, $season);
    } elseif ($path === '/api/usage') {
        (new SyncController())->getUsage();
    } elseif ($path === '/api/filters') {
        (new FilterController())->getFilters();
    } elseif (preg_match('#^/api/bets/delete/(\d+)$#', $path, $matches)) {
        header('Content-Type: application/json');
        $success = (new \App\Models\Bet())->delete($matches[1]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } elseif ($path === '/api/bets/deduplicate') {
        header('Content-Type: application/json');
        $success = (new \App\Models\Bet())->deduplicate();
        echo json_encode(['status' => 'success']);
    } elseif ($path === '/api/betfair/balance') {
        (new BetController())->getRealBalance();
    } elseif ($path === '/api/betfair/orders') {
        (new BetController())->getOrders();
    } elseif ($path === '/api/betfair/account') {
        (new MatchController())->getAccount();
    } elseif ($path === '/api/betfair/sports') {
        (new MatchController())->getSports();
    } elseif ($path === '/api/migrate') {
        header('Content-Type: application/json');
        try {
            // Invece di due alter table, eseguiamo direttamente la logica di fix_db.php
            ob_start();
            require __DIR__ . '/fix_db.php';
            $output = ob_get_clean();
            echo json_encode(['status' => 'success', 'message' => 'Schema updated via fix_db.php', 'output' => $output]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($path === '/api/settings' && $method === 'GET') {
        (new \App\Controllers\SystemController())->getSettings();
    } elseif ($path === '/api/settings/update' && $method === 'POST') {
        (new \App\Controllers\SystemController())->updateSettings();
    } elseif ($path === '/api/simulation/reset' && $method === 'POST') {
        (new \App\Controllers\SystemController())->resetSimulation();
    } elseif ($path === '/api/view/settings') {
        (new \App\Controllers\SystemController())->viewSettings();
    } elseif ($path === '/api/view/tracker') {
        (new \App\Controllers\BetController())->viewTracker();
    } elseif (preg_match('#^/api/view/modal/bet/(\d+)$#', $path, $matches)) {
        (new \App\Controllers\BetController())->viewBetModal($matches[1]);
    } elseif ($path === '/api/view/modal/place_bet') {
        (new \App\Controllers\BetController())->viewPlaceBetModal();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'path' => $path]);
    }
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server Error',
        'message' => $e->getMessage()
    ]);
}

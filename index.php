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

$request = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Basic Routing
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/scommetto', '', $path); // Adjust if running in a subdirectory

try {
    // Gestione viste standard tramite Controller dedicati
    $viewRoutes = [
        '/countries' => CountryController::class,
        '/leagues' => LeagueController::class,
        '/seasons' => SeasonController::class,
        '/teams' => TeamController::class,
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

    if (isset($viewRoutes[$path])) {
        (new $viewRoutes[$path]())->index();
        return;
    }

    if (
        $path === '/' || $path === '/index.php' || $path === '' ||
        in_array(rtrim($path, '/'), ['/dashboard', '/leagues', '/predictions', '/tracker', '/settings']) ||
        preg_match('#^/(match|team|player)/(\d+)$#', $path)
    ) {
        (new MatchController())->index();
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
    } elseif ($path === '/api/h2h') {
        (new H2HController())->show();
    } elseif ($path === '/api/teams') {
        (new TeamController())->list();
    } elseif ($path === '/api/team/seasons') {
        (new TeamController())->seasons();
    } elseif ($path === '/api/team-stats') {
        (new TeamStatsController())->show();
    } elseif ($path === '/api/standings') {
        (new StandingController())->list();
    } elseif ($path === '/api/venues') {
        (new VenueController())->list();
    } elseif ($path === '/api/dashboard' || $path === '/api/view/dashboard') {
        (new MatchController())->dashboard();
    } elseif ($path === '/api/view/leagues') {
        (new MatchController())->viewLeagues();
    } elseif (preg_match('#^/api/view/leagues/(\d+)$#', $path, $matches)) {
        (new MatchController())->viewLeagueDetails($matches[1]);
    } elseif ($path === '/api/view/predictions') {
        (new MatchController())->viewPredictions();
    } elseif ($path === '/api/view/tracker') {
        (new App\Controllers\BetController())->viewTracker();
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
    } elseif (preg_match('#^/api/match/(\d+)$#', $path, $matches)) {
        (new MatchController())->getMatch($matches[1]);
    } elseif (preg_match('#^/api/analyze/(\d+)$#', $path, $matches)) {
        (new MatchController())->analyze($matches[1]);
    } elseif (preg_match('#^/api/predictions/(\d+)$#', $path, $matches)) {
        (new MatchController())->getPrediction($matches[1]);
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

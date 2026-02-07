<?php
// index.php

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\MatchController;
use App\Controllers\BetController;
use App\Controllers\SyncController;
use App\Controllers\FilterController;

$request = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Basic Routing
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/scommetto', '', $path); // Adjust if running in a subdirectory

try {
    if ($path === '/' || $path === '/index.php' || $path === '') {
        (new MatchController())->index();
    } elseif ($path === '/api/live') {
        (new MatchController())->getLive();
    } elseif ($path === '/api/dashboard' || $path === '/api/view/dashboard') {
        (new MatchController())->dashboard();
    } elseif ($path === '/api/view/leagues') {
        (new MatchController())->viewLeagues();
    } elseif ($path === '/api/upcoming') {
        (new MatchController())->getUpcoming();
    } elseif ($path === '/api/history') {
        (new BetController())->getHistory();
    } elseif (preg_match('#^/api/match/(\d+)$#', $path, $matches)) {
        (new MatchController())->getMatch($matches[1]);
    } elseif (preg_match('#^/api/analyze/(\d+)$#', $path, $matches)) {
        (new MatchController())->analyze($matches[1]);
    } elseif (preg_match('#^/api/predictions/(\d+)$#', $path, $matches)) {
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
    } elseif ($path === '/api/migrate') {
        header('Content-Type: application/json');
        $db = \App\Services\Database::getInstance()->getConnection();
        try {
            $db->exec("ALTER TABLE bets ADD COLUMN bookmaker_id INT NULL AFTER fixture_id");
            $db->exec("ALTER TABLE bets ADD COLUMN bookmaker_name VARCHAR(100) NULL AFTER bookmaker_id");
            echo json_encode(['status' => 'success', 'message' => 'Schema updated']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'already_updated', 'message' => $e->getMessage()]);
        }
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

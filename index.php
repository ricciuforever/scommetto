<?php
// index.php

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\MatchController;
use App\Controllers\BetController;
use App\Controllers\SyncController;

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
    } elseif ($path === '/api/history') {
        (new BetController())->getHistory();
    } elseif (preg_match('#^/api/analyze/(\d+)$#', $path, $matches)) {
        (new MatchController())->analyze($matches[1]);
    } elseif (preg_match('#^/api/predictions/(\d+)$#', $path, $matches)) {
        (new MatchController())->getPredictions($matches[1]);
    } elseif (preg_match('#^/api/standings/(\d+)$#', $path, $matches)) {
        (new MatchController())->getStandings($matches[1]);
    } elseif (preg_match('#^/api/team/(\d+)$#', $path, $matches)) {
        (new MatchController())->getTeamDetails($matches[1]);
    } elseif (preg_match('#^/api/player/(\d+)$#', $path, $matches)) {
        (new MatchController())->getPlayerDetails($matches[1]);
    } elseif ($path === '/api/place_bet' && $method === 'POST') {
        (new BetController())->placeBet();
    } elseif ($path === '/api/sync') {
        (new SyncController())->sync();
    } elseif ($path === '/api/deep-sync') {
        $league = $_GET['league'] ?? 135;
        
        // Calcolo dinamico: se siamo a inizio anno (<= giugno), la stagione API è l'anno precedente
        $defaultSeason = (int)date('m') <= 6 ? (int)date('Y') - 1 : (int)date('Y');
        
        // Prende la stagione dall'URL se presente (?season=2025), altrimenti usa quella dinamica
        $season = isset($_GET['season']) ? (int)$_GET['season'] : $defaultSeason;
        
        (new SyncController())->deepSync($league, $season);
    } elseif ($path === '/api/usage') {
        (new SyncController())->getUsage();
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

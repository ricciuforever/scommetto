<?php
// backend/api.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gemini.php';

$request = $_SERVER['REQUEST_URI'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Simple Router - Matches the end of the URL
if (strpos($request, '/live') !== false) {
    if (file_exists(LIVE_DATA_FILE)) {
        $data = json_decode(file_get_contents(LIVE_DATA_FILE), true);
        $data['server_time'] = filemtime(LIVE_DATA_FILE);
        echo json_encode($data);
    } else {
        echo json_encode(["response" => []]);
    }
} elseif (strpos($request, '/history') !== false) {
    if (file_exists(BETS_HISTORY_FILE)) {
        echo file_get_contents(BETS_HISTORY_FILE);
    } else {
        echo json_encode([]);
    }
} elseif (strpos($request, '/teams') !== false) {
    if (file_exists(__DIR__ . '/serie_a_teams.json')) {
        echo file_get_contents(__DIR__ . '/serie_a_teams.json');
    } else {
        echo json_encode(["response" => []]);
    }
} elseif (strpos($request, '/logs') !== false) {
    if (file_exists(LOG_FILE)) {
        $lines = file(LOG_FILE);
        echo json_encode(["logs" => array_slice($lines, -20)]);
    } else {
        echo json_encode(["logs" => ["Log file not found."]]);
    }
} elseif (strpos($request, '/analyze') !== false) {
    // Extract ID (matches /analyze/12345)
    preg_match('/\/analyze\/(\d+)/', $request, $matches);
    $fid = $matches[1] ?? null;
    if (!$fid) {
        echo json_encode(["error" => "Missing fixture ID"]);
        exit;
    }

    $live = json_decode(file_exists(LIVE_DATA_FILE) ? file_get_contents(LIVE_DATA_FILE) : '{"response":[]}', true);
    $match_data = null;
    foreach ($live['response'] ?? [] as $m) {
        if ($m['fixture']['id'] == $fid) {
            $match_data = $m;
            break;
        }
    }

    if (!$match_data) {
        echo json_encode(["error" => "Match not found in live data"]);
        exit;
    }

    $prediction = analyze_with_gemini($match_data);
    echo json_encode([
        "fixture_id" => $fid,
        "prediction" => $prediction,
        "raw_data" => $match_data,
        "auto_bet_status" => "manual_only_in_php"
    ]);
} elseif (strpos($request, '/place_bet') !== false && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input)
        exit;

    $history = file_exists(BETS_HISTORY_FILE) ? json_decode(file_get_contents(BETS_HISTORY_FILE), true) : [];

    $input['id'] = (string) (count($history) + 1);
    $input['status'] = 'pending';
    $input['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');

    $history[] = $input;
    file_put_contents(BETS_HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));
    echo json_encode(["status" => "success", "bet" => $input]);
} elseif (strpos($request, '/usage') !== false) {
    if (file_exists(__DIR__ . '/usage.json')) {
        echo file_get_contents(__DIR__ . '/usage.json');
    } else {
        echo json_encode(["used" => 0, "remaining" => 7500]);
    }
} else {
    $usage = ["used" => 0, "remaining" => 7500];
    if (file_exists(__DIR__ . '/usage.json')) {
        $usage = json_decode(file_get_contents(__DIR__ . '/usage.json'), true);
    }
    echo json_encode(["status" => "Backend PHP is alive", "path" => $request, "usage" => $usage]);
}

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

// Simple Router
if (strpos($request, 'api/live') !== false) {
    if (file_exists(LIVE_DATA_FILE)) {
        $data = json_decode(file_get_contents(LIVE_DATA_FILE), true);
        $data['server_time'] = filemtime(LIVE_DATA_FILE);
        echo json_encode($data);
    } else {
        echo json_encode(["response" => []]);
    }
} elseif (strpos($request, 'api/history') !== false) {
    if (file_exists(BETS_HISTORY_FILE)) {
        echo file_get_contents(BETS_HISTORY_FILE);
    } else {
        echo json_encode([]);
    }
} elseif (strpos($request, 'api/teams') !== false) {
    if (file_exists(__DIR__ . '/serie_a_teams.json')) {
        echo file_get_contents(__DIR__ . '/serie_a_teams.json');
    } else {
        echo json_encode(["response" => []]);
    }
} elseif (strpos($request, 'api/logs') !== false) {
    if (file_exists(LOG_FILE)) {
        $lines = file(LOG_FILE);
        echo json_encode(["logs" => array_slice($lines, -20)]);
    } else {
        echo json_encode(["logs" => ["Log file not found."]]);
    }
} elseif (strpos($request, 'api/analyze') !== false) {
    // Extract ID
    preg_match('/api\/analyze\/(\d+)/', $request, $matches);
    $fid = $matches[1] ?? null;
    if (!$fid) {
        echo json_encode(["error" => "Missing fixture ID"]);
        exit;
    }

    // Simulate get_fixture_details (just use live data if available for now)
    $live = json_decode(file_exists(LIVE_DATA_FILE) ? file_get_contents(LIVE_DATA_FILE) : '{"response":[]}', true);
    $match_data = null;
    foreach ($live['response'] as $m) {
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
} elseif (strpos($request, 'api/place_bet') !== false && $method === 'POST') {
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
} elseif (strpos($request, 'api/usage') !== false) {
    // Fake usage for PHP version as we don't track headers easily here
    echo json_encode(["used" => 0, "remaining" => 7500]);
} else {
    echo json_encode(["status" => "Backend PHP is alive", "path" => $request]);
}

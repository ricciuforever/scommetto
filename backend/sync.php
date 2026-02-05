<?php
// backend/sync.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gemini.php';

log_msg("--- START SYNC (PHP) ---");

// 1. FETCH LIVE DATA
function fetch_live_data()
{
    $ch = curl_init(BASE_URL . "/fixtures?live=all");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-rapidapi-host: v3.football.api-sports.io',
        'x-rapidapi-key: ' . FOOTBALL_API_KEY
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        file_put_contents(LIVE_DATA_FILE, $res);
        log_msg("Live data updated.");
        return json_decode($res, true);
    }
    log_msg("Error fetching live data.");
    return null;
}

// 2. SETTLE BETS
function settle_bets()
{
    if (!file_exists(BETS_HISTORY_FILE))
        return;
    $history = json_decode(file_get_contents(BETS_HISTORY_FILE), true);
    if (!$history)
        return;

    $pending = array_filter($history, function ($b) {
        return ($b['status'] ?? '') === 'pending';
    });

    if (empty($pending))
        return;

    // Chunk IDs for Batch API (max 20)
    $ids = array_map(function ($b) {
        return $b['fixture_id']; }, $pending);
    $chunks = array_chunk(array_unique($ids), 20);

    $fixtures_data = [];
    foreach ($chunks as $chunk) {
        $ch = curl_init(BASE_URL . "/fixtures?ids=" . implode(',', $chunk));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-rapidapi-host: v3.football.api-sports.io',
            'x-rapidapi-key: ' . FOOTBALL_API_KEY
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        foreach ($data['response'] ?? [] as $f) {
            $fixtures_data[$f['fixture']['id']] = $f;
        }
    }

    $updated = false;
    foreach ($history as &$bet) {
        if (($bet['status'] ?? '') !== 'pending')
            continue;

        $fid = $bet['fixture_id'];
        if (!isset($fixtures_data[$fid])) {
            // Check if bet is too old (e.g. > 4 hours)
            $bet_time = strtotime($bet['timestamp']);
            if (time() - $bet_time > 14400) { // 4 hours
                $bet['status'] = 'stale';
                $updated = true;
                log_msg("Stale bet: " . $bet['match']);
            }
            continue;
        }

        $f = $fixtures_data[$fid];
        $status = $f['fixture']['status']['short'];
        $h = $f['goals']['home'];
        $a = $f['goals']['away'];
        $advice = strtolower($bet['advice'] ?? '');
        $home_name = strtolower($f['teams']['home']['name'] ?? '');
        $away_name = strtolower($f['teams']['away']['name'] ?? '');

        // Basic Settlement Logic
        if (in_array($status, ['FT', 'AET', 'PEN'])) {
            $is_win = false;
            // Precise Win Logic
            if ((strpos($advice, '1') !== false || strpos($advice, 'casa') !== false || strpos($advice, 'home') !== false) && $h > $a)
                $is_win = true;
            elseif ((strpos($advice, '2') !== false || strpos($advice, 'trasferta') !== false || strpos($advice, 'away') !== false) && $a > $h)
                $is_win = true;
            elseif ((strpos($advice, 'x') !== false || strpos($advice, 'draw') !== false || strpos($advice, 'pareggio') !== false) && $h == $a)
                $is_win = true;
            elseif (strpos($advice, 'over') !== false && ($h + $a) > 2.5)
                $is_win = true;
            elseif (strpos($advice, 'under') !== false && ($h + $a) < 2.5)
                $is_win = true;
            elseif (strpos($advice, $home_name) !== false && $h > $a)
                $is_win = true;
            elseif (strpos($advice, $away_name) !== false && $a > $h)
                $is_win = true;

            $bet['status'] = $is_win ? 'win' : 'lost';
            $bet['result'] = "$h-$a";
            $updated = true;
            log_msg("Settled: " . $bet['match'] . " (" . $bet['status'] . ") $h-$a");
        }
    }

    if ($updated) {
        file_put_contents(BETS_HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));
    }
}

fetch_live_data();
settle_bets();

log_msg("--- END SYNC (PHP) ---");

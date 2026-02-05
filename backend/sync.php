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
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-rapidapi-host: v3.football.api-sports.io',
        'x-rapidapi-key: ' . FOOTBALL_API_KEY
    ]);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);

    if (
        preg_match('/x-ratelimit-requests-used: (\d+)/i', $headers, $m1) &&
        preg_match('/x-ratelimit-requests-remaining: (\d+)/i', $headers, $m2)
    ) {
        $usage = ['used' => (int) $m1[1], 'remaining' => (int) $m2[1], 'updated' => time()];
        file_put_contents(__DIR__ . '/usage.json', json_encode($usage));
    }

    if ($body) {
        file_put_contents(LIVE_DATA_FILE, $body);
        log_msg("Live data updated.");
        return json_decode($body, true);
    }
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

    $pending_indices = [];
    foreach ($history as $idx => $b) {
        if (($b['status'] ?? '') === 'pending')
            $pending_indices[] = $idx;
    }

    if (empty($pending_indices)) {
        log_msg("No pending bets to settle.");
        return;
    }

    $ids = [];
    foreach ($pending_indices as $idx)
        $ids[] = $history[$idx]['fixture_id'];
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
    foreach ($pending_indices as $idx) {
        $bet = &$history[$idx];
        $fid = $bet['fixture_id'];

        if (!isset($fixtures_data[$fid])) {
            if (time() - strtotime($bet['timestamp']) > 10800) {
                $bet['status'] = 'stale';
                $updated = true;
                log_msg("Marked stale: " . $bet['match']);
            }
            continue;
        }

        $f = $fixtures_data[$fid];
        $status = $f['fixture']['status']['short'];
        $h = $f['goals']['home'];
        $a = $f['goals']['away'];
        if ($h === null || $a === null)
            continue;

        if (in_array($status, ['FT', 'AET', 'PEN'])) {
            $advice = strtolower($bet['advice'] ?? '');
            $is_win = false;
            $found = false;

            if (preg_match('/\b(vittoria casa|vince casa|^1$|casa vince|home win)\b/', $advice)) {
                $found = true;
                if ($h > $a)
                    $is_win = true;
            } elseif (preg_match('/\b(vittoria ospite|vince trasferta|^2$|trasferta vince|away win)\b/', $advice)) {
                $found = true;
                if ($a > $h)
                    $is_win = true;
            } elseif (preg_match('/\b(x|draw|pareggio)\b/', $advice) && !preg_match('/\d/', str_replace('x', '', $advice))) {
                $found = true;
                if ($h == $a)
                    $is_win = true;
            } elseif (strpos($advice, 'over') !== false) {
                $found = true;
                preg_match('/(\d+\.\d+)/', $advice, $m);
                $thr = isset($m[1]) ? (float) $m[1] : 2.5;
                if (($h + $a) > $thr)
                    $is_win = true;
            } elseif (strpos($advice, 'under') !== false) {
                $found = true;
                preg_match('/(\d+\.\d+)/', $advice, $m);
                $thr = isset($m[1]) ? (float) $m[1] : 2.5;
                if (($h + $a) < $thr)
                    $is_win = true;
            }

            if ($found) {
                $bet['status'] = $is_win ? 'win' : 'lost';
                $bet['result'] = "$h-$a";
                $updated = true;
                log_msg("SETTLED: {$bet['match']} -> " . strtoupper($bet['status']) . " ($h-$a)");
            } else {
                $bet['status'] = 'lost';
                $bet['result'] = "$h-$a (Ambig)";
                $updated = true;
                log_msg("SETTLED (Ambig): {$bet['match']} -> LOST ($h-$a)");
            }
        }
    }

    if ($updated) {
        file_put_contents(BETS_HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));
    }
}

fetch_live_data();
settle_bets();

log_msg("--- END SYNC (PHP) ---");

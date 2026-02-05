<?php
// backend/config.php

// Carica variabili da .env se esiste
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . "=" . trim($value));
        }
    }
}

define('FOOTBALL_API_KEY', getenv('FOOTBALL_API_KEY'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('BASE_URL', 'https://v3.football.api-sports.io');

define('LIVE_DATA_FILE', __DIR__ . '/live_matches.json');
define('BETS_HISTORY_FILE', __DIR__ . '/bets_history.json');
define('LOG_FILE', __DIR__ . '/agent_log.txt');

function log_msg($msg)
{
    $date = date('Y-m-d H:i:s');
    $full_msg = "[$date] $msg\n";
    echo $full_msg;
    file_put_contents(LOG_FILE, $full_msg, FILE_APPEND);
}

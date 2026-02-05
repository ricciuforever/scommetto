<?php
// app/Config/Config.php

namespace App\Config;

class Config {
    public static function init() {
        if (file_exists(__DIR__ . '/../../.env')) {
            $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $_ENV[trim($name)] = trim($value);
                    putenv(trim($name) . "=" . trim($value));
                }
            }
        }
    }

    public static function get($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public const FOOTBALL_API_BASE_URL = 'https://v3.football.api-sports.io';
    public const DATA_PATH = __DIR__ . '/../../data/';
    public const LIVE_DATA_FILE = self::DATA_PATH . 'live_matches.json';
    public const BETS_HISTORY_FILE = self::DATA_PATH . 'bets_history.json';
    public const LOG_FILE = self::DATA_PATH . 'agent_log.txt';
    public const USAGE_FILE = self::DATA_PATH . 'usage.json';
}

<?php
// app/Config/Config.php

namespace App\Config;

class Config
{
    const FOOTBALL_API_BASE_URL = 'https://v3.football.api-sports.io';
    const LOGS_PATH = __DIR__ . '/../../logs/';
    const LOG_FILE = self::LOGS_PATH . 'app_error.log';
    const DATA_PATH = __DIR__ . '/../../data/';
    const LIVE_DATA_FILE = self::DATA_PATH . 'live_matches.json';
    const BETS_HISTORY_FILE = self::DATA_PATH . 'bets_history.json';
    const USAGE_FILE = self::DATA_PATH . 'usage.json';
    const INITIAL_BANKROLL = 100.00;
    const MIN_BETFAIR_STAKE = 2.00;
    const BETFAIR_CONFIDENCE_THRESHOLD = 50;

    // Popular League IDs
    const LEAGUE_SERIE_A = 135;
    const LEAGUE_PREMIER = 39;
    const LEAGUE_LA_LIGA = 140;
    const LEAGUE_BUNDESLIGA = 78;
    const LEAGUE_LIGUE_1 = 61;
    const LEAGUE_CHAMPIONS = 2;
    const LEAGUE_EUROPA = 3;

    const PREMIUM_LEAGUES = [
        135, // Serie A
        39,  // Premier
        140, // La Liga
        78,  // Bundesliga
        61   // Ligue 1
    ];

    private static $env = [];

    public static function init()
    {
        if (file_exists(__DIR__ . '/../../.env')) {
            $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0)
                    continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    self::$env[$name] = $value;
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
    }

    public static function get($key, $default = null)
    {
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function getCurrentSeason()
    {
        return (int) date('m') <= 6 ? (int) date('Y') - 1 : (int) date('Y');
    }
}

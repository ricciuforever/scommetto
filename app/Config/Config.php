<?php
// app/Config/Config.php

namespace App\Config;

class Config
{
    const FOOTBALL_API_BASE_URL = 'https://v3.football.api-sports.io';
    const BASKETBALL_API_BASE_URL = 'https://v1.basketball.api-sports.io';
    const NBA_API_BASE_URL = 'https://v2.nba.api-sports.io';
    const LOGS_PATH = __DIR__ . '/../../logs/';
    const LOG_FILE = self::LOGS_PATH . 'app_error.log';
    const DATA_PATH = __DIR__ . '/../../data/';
    const LIVE_DATA_FILE = self::DATA_PATH . 'live_matches.json';
    const BETS_HISTORY_FILE = self::DATA_PATH . 'bets_history.json';
    const USAGE_FILE = self::DATA_PATH . 'usage.json';
    const SETTINGS_FILE = self::DATA_PATH . 'settings.json'; // New Settings File
    const DEFAULT_INITIAL_BANKROLL = 100.00;
    const DEFAULT_VIRTUAL_BOOKMAKER_ID = 7; // William Hill
    const MIN_BETFAIR_STAKE = 2.00;
    const BETFAIR_CONFIDENCE_THRESHOLD = 80;
    const DEFAULT_TIMEZONE = 'Europe/Rome';

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

    public static function getSettings()
    {
        if (!file_exists(self::SETTINGS_FILE)) {
            return [
                'simulation_mode' => true,
                'initial_bankroll' => self::DEFAULT_INITIAL_BANKROLL,
                'virtual_bookmaker_id' => self::DEFAULT_VIRTUAL_BOOKMAKER_ID
            ];
        }
        $settings = json_decode(file_get_contents(self::SETTINGS_FILE), true);
        return [
            'simulation_mode' => $settings['simulation_mode'] ?? true,
            'initial_bankroll' => (float) ($settings['initial_bankroll'] ?? self::DEFAULT_INITIAL_BANKROLL),
            'virtual_bookmaker_id' => (int) ($settings['virtual_bookmaker_id'] ?? self::DEFAULT_VIRTUAL_BOOKMAKER_ID)
        ];
    }

    public static function isSimulationMode()
    {
        $settings = self::getSettings();
        return $settings['simulation_mode'];
    }

    public static function getInitialBankroll()
    {
        $settings = self::getSettings();
        return $settings['initial_bankroll'];
    }

    public static function getVirtualBookmakerId()
    {
        $settings = self::getSettings();
        return $settings['virtual_bookmaker_id'];
    }

    public static function getDB()
    {
        static $pdo = null;
        if ($pdo === null) {
            // MySQL Configuration
            $dsn = "mysql:host=localhost;dbname=scommetto;charset=utf8mb4";
            $username = "root";
            $password = "";

            try {
                $pdo = new \PDO($dsn, $username, $password);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // If MySQL fails, try SQLite as fallback or just log error
                error_log("MySQL Connection failed: " . $e->getMessage());
                throw $e;
            }
        }
        return $pdo;
    }
}

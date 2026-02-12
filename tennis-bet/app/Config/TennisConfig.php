<?php
// tennis-bet/app/Config/TennisConfig.php

namespace TennisApp\Config;

class TennisConfig
{
    const BASE_PATH = __DIR__ . '/../../';
    const DATA_PATH = self::BASE_PATH . 'data/';
    const LOGS_PATH = self::BASE_PATH . 'logs/';
    const DB_PATH = self::BASE_PATH . 'app/Database/tennis.sqlite';
    const LOG_FILE = self::LOGS_PATH . 'tennis_error.log';

    const INITIAL_VIRTUAL_BALANCE = 100.00;
    const MIN_ODDS = 1.25;
    const DEFAULT_STAKE = 10.00;
    const CONFIDENCE_THRESHOLD = 75;

    // Betfair Tennis Event Type ID
    const TENNIS_EVENT_TYPE_ID = "2";

    // URLs for Jeff Sackmann's data
    const JEFF_SACKMANN_ATP_BASE = 'https://raw.githubusercontent.com/JeffSackmann/tennis_atp/master/';
    const JEFF_SACKMANN_WTA_BASE = 'https://raw.githubusercontent.com/JeffSackmann/tennis_wta/master/';

    private static $env = [];

    public static function init()
    {
        // Use the main .env file if it exists
        $mainEnv = __DIR__ . '/../../../.env';
        if (file_exists($mainEnv)) {
            $lines = file($mainEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0)
                    continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    self::$env[$name] = $value;
                }
            }
        }
    }

    public static function get($key, $default = null)
    {
        return self::$env[$key] ?? getenv($key) ?: $default;
    }

    public static function getDB()
    {
        static $pdo = null;
        if ($pdo === null) {
            try {
                $pdo = new \PDO("sqlite:" . self::DB_PATH);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log("Tennis SQLite Connection failed: " . $e->getMessage());
                throw $e;
            }
        }
        return $pdo;
    }
}

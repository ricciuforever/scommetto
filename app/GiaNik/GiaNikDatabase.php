<?php
// app/GiaNik/GiaNikDatabase.php

namespace App\GiaNik;

use PDO;
use App\Config\Config;

class GiaNikDatabase
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $dbPath = Config::DATA_PATH . 'gianik.sqlite';
        $this->connection = new PDO("sqlite:$dbPath");
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS bets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            market_id TEXT,
            event_name TEXT,
            sport TEXT,
            selection_id TEXT,
            runner_name TEXT,
            odds REAL,
            stake REAL,
            status TEXT DEFAULT 'pending',
            type TEXT DEFAULT 'virtual',
            betfair_id TEXT,
            motivation TEXT,
            profit REAL DEFAULT 0,
            settled_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}

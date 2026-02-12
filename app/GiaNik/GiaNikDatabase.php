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
        // 1. Ensure table exists
        $this->connection->exec("CREATE TABLE IF NOT EXISTS bets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            market_id TEXT,
            market_name TEXT,
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

        $this->connection->exec("CREATE TABLE IF NOT EXISTS system_state (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Ensure all columns exist (Self-Repair)
        $requiredColumns = [
            'profit' => 'REAL DEFAULT 0',
            'commission' => 'REAL DEFAULT 0',
            'settled_at' => 'DATETIME',
            'motivation' => 'TEXT',
            'type' => "TEXT DEFAULT 'virtual'",
            'market_name' => 'TEXT'
        ];

        $stmt = $this->connection->query("PRAGMA table_info(bets)");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        foreach ($requiredColumns as $col => $definition) {
            if (!in_array($col, $existingColumns)) {
                try {
                    $this->connection->exec("ALTER TABLE bets ADD COLUMN $col $definition");
                } catch (\Exception $e) {
                    // Ignore if already exists or other sqlite issues
                }
            }
        }
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

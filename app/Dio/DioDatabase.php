<?php
// app/Dio/DioDatabase.php

namespace App\Dio;

use PDO;
use App\Config\Config;

class DioDatabase
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $dbPath = Config::DATA_PATH . 'dio.sqlite';
        $this->connection = new PDO("sqlite:$dbPath");
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        // Bets table
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
            status TEXT DEFAULT 'pending', -- 'pending', 'won', 'lost', 'void'
            type TEXT DEFAULT 'virtual',
            betfair_id TEXT,
            motivation TEXT,
            profit REAL DEFAULT 0,
            settled_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Logs table for "Thinking" transparency
        $this->connection->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_name TEXT,
            market_name TEXT,
            selection_name TEXT,
            confidence INTEGER,
            action TEXT, -- 'bet', 'pass'
            motivation TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Experiences table for RAG Brain
        $this->connection->exec("CREATE TABLE IF NOT EXISTS experiences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sport TEXT,
            market_type TEXT,
            outcome TEXT, -- 'won', 'lost', 'void'
            lesson TEXT,
            data_context TEXT, -- JSON snapshot of market state at bet time
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // System state for balance, etc.
        $this->connection->exec("CREATE TABLE IF NOT EXISTS system_state (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Initialize Virtual Balance if not exists
        $stmt = $this->connection->prepare("SELECT value FROM system_state WHERE key = 'virtual_balance'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $this->connection->exec("INSERT INTO system_state (key, value) VALUES ('virtual_balance', '100.00')");
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

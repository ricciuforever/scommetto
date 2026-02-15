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
            score TEXT,
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

        // Repair system_state if updated_at is missing (legacy)
        $stmtState = $this->connection->query("PRAGMA table_info(system_state)");
        $existingStateCols = $stmtState->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('updated_at', $existingStateCols)) {
            try {
                $this->connection->exec("ALTER TABLE system_state ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            } catch (\Exception $e) {}
        }

        // Migration: Ensure all bets have a type (legacy)
        $this->connection->exec("UPDATE bets SET type = 'virtual' WHERE type IS NULL OR type = ''");

        // Ensure default dynamic configuration
        $defaults = [
            'strategy_prompt' => (new \App\Services\GeminiService())->getDefaultStrategyPrompt('dio'),
            'stake_mode' => 'kelly',
            'stake_value' => '0.10',
            'min_confidence' => '80',
            'min_liquidity' => '5000.00',
            'daily_stop_loss' => '100.00',
            'virtual_balance' => '100.00',
            'operational_mode' => 'virtual',
            'target_sports' => '1'
        ];

        foreach ($defaults as $key => $val) {
            $stmt = $this->connection->prepare("INSERT OR IGNORE INTO system_state (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
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

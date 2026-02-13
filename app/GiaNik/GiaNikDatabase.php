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

        $this->connection->exec("CREATE TABLE IF NOT EXISTS performance_metrics (
            context_type TEXT NOT NULL,
            context_id TEXT NOT NULL,
            total_bets INTEGER DEFAULT 0,
            wins INTEGER DEFAULT 0,
            losses INTEGER DEFAULT 0,
            total_stake REAL DEFAULT 0.0,
            total_profit REAL DEFAULT 0.0,
            roi REAL DEFAULT 0.0,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (context_type, context_id)
        )");

        $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_perf_metrics ON performance_metrics(context_type, context_id)");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS ai_lessons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT, -- 'team', 'league', 'strategy'
            entity_id TEXT,
            lesson_text TEXT,
            context_snapshot TEXT, -- JSON
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS match_snapshots (
            fixture_id INTEGER,
            minute INTEGER,
            home_shots INTEGER,
            away_shots INTEGER,
            home_corners INTEGER,
            away_corners INTEGER,
            home_possession INTEGER,
            away_possession INTEGER,
            dangerous_attacks_home INTEGER,
            dangerous_attacks_away INTEGER,
            stats_json TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (fixture_id, minute)
        )");

        // 2. Ensure all columns exist (Self-Repair)
        $requiredColumns = [
            'profit' => 'REAL DEFAULT 0',
            'commission' => 'REAL DEFAULT 0',
            'settled_at' => 'DATETIME',
            'motivation' => 'TEXT',
            'type' => "TEXT DEFAULT 'virtual'",
            'market_name' => 'TEXT',
            'needs_analysis' => 'INTEGER DEFAULT 0',
            'bucket' => 'TEXT',
            'league' => 'TEXT',
            'league_id' => 'INTEGER'
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

        // Repair performance_metrics (Handle migration to composite key if needed)
        $stmt = $this->connection->query("PRAGMA table_info(performance_metrics)");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (in_array('metric_key', $cols)) {
            // Old schema detected, we need to migrate or just reset (as per Lead Dev's "reset metrics" idea)
            $this->connection->exec("DROP TABLE performance_metrics");
            $this->ensureSchema(); // Re-run to create new table
            return;
        }

        // Repair match_snapshots
        $requiredSnapshotColumns = [
            'minute' => 'INTEGER',
            'home_shots' => 'INTEGER',
            'away_shots' => 'INTEGER',
            'home_corners' => 'INTEGER',
            'away_corners' => 'INTEGER',
            'home_possession' => 'INTEGER',
            'away_possession' => 'INTEGER',
            'dangerous_attacks_home' => 'INTEGER',
            'dangerous_attacks_away' => 'INTEGER',
            'stats_json' => 'TEXT',
            'timestamp' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ];
        $stmt = $this->connection->query("PRAGMA table_info(match_snapshots)");
        $existingSnapshotColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ($requiredSnapshotColumns as $col => $definition) {
            if (!in_array($col, $existingSnapshotColumns)) {
                try {
                    $this->connection->exec("ALTER TABLE match_snapshots ADD COLUMN $col $definition");
                } catch (\Exception $e) {
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

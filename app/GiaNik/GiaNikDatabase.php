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

        // Ensure default dynamic configuration
        $defaults = [
            'strategy_prompt' => "Sei GiaNik, un esperto di scommesse sportive. Analizza i dati live e storici per trovare Value Bets.",
            'stake_mode' => 'kelly',
            'stake_value' => '0.15',
            'min_confidence' => '80',
            'daily_stop_loss' => '50.00'
        ];

        foreach ($defaults as $key => $val) {
            $stmt = $this->connection->prepare("INSERT OR IGNORE INTO system_state (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        }

        // Check for old performance_metrics schema
        $stmtCheck = $this->connection->query("PRAGMA table_info(performance_metrics)");
        $checkCols = $stmtCheck->fetchAll(PDO::FETCH_COLUMN, 1);
        if (in_array('metric_key', $checkCols)) {
            $this->connection->exec("DROP TABLE performance_metrics");
        }

        $this->connection->exec("CREATE TABLE IF NOT EXISTS performance_metrics (
            context_type TEXT NOT NULL,
            context_id TEXT NOT NULL,
            total_bets INTEGER DEFAULT 0,
            wins INTEGER DEFAULT 0,
            losses INTEGER DEFAULT 0,
            total_stake REAL DEFAULT 0.0,
            profit_loss REAL DEFAULT 0.0,
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
            match_context TEXT,
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
            'is_learned' => 'INTEGER DEFAULT 0',
            'bucket' => 'TEXT',
            'league' => 'TEXT',
            'league_id' => 'INTEGER',
            'fixture_id' => 'INTEGER',
            'size_matched' => 'REAL DEFAULT 0',
            'placed_at_minute' => 'INTEGER',
            'placed_at_period' => 'TEXT'
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


        // Repair performance_metrics
        $stmtPerf = $this->connection->query("PRAGMA table_info(performance_metrics)");
        $existingPerfCols = $stmtPerf->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('profit_loss', $existingPerfCols) && in_array('total_profit', $existingPerfCols)) {
            try {
                $this->connection->exec("ALTER TABLE performance_metrics RENAME COLUMN total_profit TO profit_loss");
            } catch (\Exception $e) {
                $this->connection->exec("ALTER TABLE performance_metrics ADD COLUMN profit_loss REAL DEFAULT 0.0");
                $this->connection->exec("UPDATE performance_metrics SET profit_loss = total_profit");
            }
        } elseif (!in_array('profit_loss', $existingPerfCols)) {
            $this->connection->exec("ALTER TABLE performance_metrics ADD COLUMN profit_loss REAL DEFAULT 0.0");
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

        // Repair ai_lessons
        $requiredLessonColumns = [
            'match_context' => 'TEXT'
        ];
        $stmt = $this->connection->query("PRAGMA table_info(ai_lessons)");
        $existingLessonColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ($requiredLessonColumns as $col => $definition) {
            if (!in_array($col, $existingLessonColumns)) {
                try {
                    $this->connection->exec("ALTER TABLE ai_lessons ADD COLUMN $col $definition");
                } catch (\Exception $e) {
                }
            }
        }

        // Ensure Soccer-Only consistency
        $this->connection->exec("UPDATE bets SET sport = 'Soccer' WHERE sport IS NULL");
        $this->connection->exec("DELETE FROM bets WHERE sport NOT IN ('Soccer', 'Football')");
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

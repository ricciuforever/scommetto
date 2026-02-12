<?php
// app/GiaNik/GiaNikDatabase.php

namespace App\GiaNik;

use PDO;
use App\Config\Config;
use App\Services\Database;

class GiaNikDatabase
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // Usa la connessione al database centrale (MySQL con fallback SQLite)
        $this->connection = Database::getInstance()->getConnection();
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $isSQLite = Database::getInstance()->isSQLite();
        $autoInc = $isSQLite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
        $defaultTs = $isSQLite ? "DATETIME DEFAULT CURRENT_TIMESTAMP" : "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";

        // 1. Ensure tables exist with prefix 'gianik_'
        $this->connection->exec("CREATE TABLE IF NOT EXISTS gianik_bets (
            id $autoInc,
            market_id VARCHAR(100),
            market_name VARCHAR(255),
            event_name VARCHAR(255),
            sport VARCHAR(50),
            selection_id VARCHAR(100),
            runner_name VARCHAR(255),
            odds REAL,
            stake REAL,
            status VARCHAR(50) DEFAULT 'pending',
            type VARCHAR(20) DEFAULT 'virtual',
            betfair_id VARCHAR(100),
            motivation TEXT,
            profit REAL DEFAULT 0,
            settled_at DATETIME,
            period VARCHAR(20),
            last_seen_at $defaultTs,
            missing_count INTEGER DEFAULT 0,
            created_at $defaultTs
        )");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS gianik_system_state (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT,
            updated_at $defaultTs
        )");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS gianik_skipped_matches (
            id $autoInc,
            event_name VARCHAR(255),
            market_name VARCHAR(255),
            reason VARCHAR(255),
            details TEXT,
            created_at $defaultTs
        )");

        // 2. Ensure all columns exist (Self-Repair for gianik_bets)
        $requiredColumns = [
            'profit' => 'REAL DEFAULT 0',
            'settled_at' => 'DATETIME',
            'motivation' => 'TEXT',
            'type' => "VARCHAR(20) DEFAULT 'virtual'",
            'market_name' => 'VARCHAR(255)',
            'period' => 'VARCHAR(20)',
            'last_seen_at' => $defaultTs,
            'missing_count' => 'INTEGER DEFAULT 0'
        ];

        if ($isSQLite) {
            $stmt = $this->connection->query("PRAGMA table_info(gianik_bets)");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        } else {
            $stmt = $this->connection->prepare("DESCRIBE gianik_bets");
            $stmt->execute();
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        foreach ($requiredColumns as $col => $definition) {
            if (!in_array($col, $existingColumns)) {
                try {
                    $this->connection->exec("ALTER TABLE gianik_bets ADD COLUMN $col $definition");
                } catch (\Exception $e) {
                    // Ignore if already exists
                }
            }
        }

        // 3. Ensure indexes exist for performance and deadlock prevention
        $indexes = [
            'idx_betfair_id' => ['betfair_id'],
            'idx_status_type' => ['status', 'type'],
            'idx_last_seen' => ['last_seen_at'],
            'idx_created_at' => ['created_at']
        ];

        foreach ($indexes as $name => $cols) {
            try {
                if ($isSQLite) {
                    $this->connection->exec("CREATE INDEX IF NOT EXISTS $name ON gianik_bets (" . implode(',', $cols) . ")");
                } else {
                    $stmt = $this->connection->prepare("SHOW INDEX FROM gianik_bets WHERE Key_name = ?");
                    $stmt->execute([$name]);
                    if (!$stmt->fetch()) {
                        $this->connection->exec("CREATE INDEX $name ON gianik_bets (" . implode(',', $cols) . ")");
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors (e.g. index already exists)
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

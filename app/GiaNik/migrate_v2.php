<?php
// app/GiaNik/migrate_v2.php

require_once __DIR__ . '/../../bootstrap.php';
use App\GiaNik\GiaNikDatabase;

try {
    $db = GiaNikDatabase::getInstance()->getConnection();

    // Add settled_at if not exists
    try {
        $db->exec("ALTER TABLE gianik_bets ADD COLUMN settled_at DATETIME");
    } catch (Exception $e) {}

    // Add profit if not exists
    try {
        $db->exec("ALTER TABLE gianik_bets ADD COLUMN profit REAL DEFAULT 0");
    } catch (Exception $e) {}

    echo "✅ Centralized GiaNik migration successful\n";
} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
}

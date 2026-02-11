<?php
// app/GiaNik/migrate_v3.php

require_once __DIR__ . '/../../bootstrap.php';
use App\GiaNik\GiaNikDatabase;

try {
    $db = GiaNikDatabase::getInstance()->getConnection();

    // Add side if not exists
    try {
        $db->exec("ALTER TABLE bets ADD COLUMN side TEXT DEFAULT 'BACK'");
    } catch (Exception $e) {}

    echo "✅ SQLite migration successful (v3)\n";
} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
}

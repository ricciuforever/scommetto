<?php
// app/GiaNik/migrate_soccer_only.php

require_once __DIR__ . '/../../bootstrap.php';
use App\GiaNik\GiaNikDatabase;

try {
    $db = GiaNikDatabase::getInstance()->getConnection();

    // Physical deletion of non-soccer bets as requested
    $count = $db->exec("DELETE FROM bets WHERE sport NOT IN ('Soccer', 'Football')");

    echo "✅ Deleted $count non-soccer bets from GiaNik database.\n";
} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
}

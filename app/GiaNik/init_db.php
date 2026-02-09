<?php
// app/GiaNik/init_db.php

$dbPath = __DIR__ . '/../../data/gianik.sqlite';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS bets (
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    echo "✅ SQLite database initialized at $dbPath\n";
} catch (PDOException $e) {
    echo "❌ Error initializing SQLite database: " . $e->getMessage() . "\n";
}

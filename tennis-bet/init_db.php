<?php
// tennis-bet/init_db.php

require_once __DIR__ . '/app/Config/TennisConfig.php';

use TennisApp\Config\TennisConfig;

TennisConfig::init();
$db = TennisConfig::getDB();

// Create Tables
$queries = [
    "CREATE TABLE IF NOT EXISTS portfolio (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        balance REAL NOT NULL,
        currency TEXT DEFAULT 'EUR',
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS bets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        market_id TEXT,
        event_name TEXT,
        advice TEXT,
        odds REAL,
        stake REAL,
        status TEXT DEFAULT 'PENDING', -- PENDING, WON, LOST, CANCELLED
        profit REAL DEFAULT 0,
        confidence INTEGER,
        motivation TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS player_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        player_id INTEGER UNIQUE,
        name TEXT,
        hand TEXT,
        country TEXT,
        rank INTEGER,
        points INTEGER,
        surface_wins_percent REAL,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS analysis_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT,
        event_name TEXT,
        analysis_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $query) {
    $db->exec($query);
}

// Initial balance check
$portfolio = $db->query("SELECT * FROM portfolio LIMIT 1")->fetch();
if (!$portfolio) {
    $initialBalance = TennisConfig::INITIAL_VIRTUAL_BALANCE;
    $db->prepare("INSERT INTO portfolio (balance) VALUES (?)")->execute([$initialBalance]);
    echo "Portfolio initialized with {$initialBalance} EUR.\n";
} elseif ($portfolio['balance'] == 1000.00) {
    // Correct legacy 1000 balance to 100
    $db->prepare("UPDATE portfolio SET balance = ?")->execute([TennisConfig::INITIAL_VIRTUAL_BALANCE]);
    echo "Portfolio updated to " . TennisConfig::INITIAL_VIRTUAL_BALANCE . " EUR.\n";
}

echo "Database initialized successfully.\n";

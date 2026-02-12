<?php
// app/GiaNik/init_db.php
require_once __DIR__ . '/../../bootstrap.php';
use App\GiaNik\GiaNikDatabase;

try {
    GiaNikDatabase::getInstance();
    echo "✅ GiaNik Centralized Database Initialized\n";
} catch (\Throwable $e) {
    echo "❌ Error initializing GiaNik database: " . $e->getMessage() . "\n";
}

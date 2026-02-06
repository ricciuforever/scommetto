<?php
// cron_3hours.php - Eseguito ogni 3 ore
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;

$sync = new SyncController();
try {
    echo "Starting 3-Hours Sync (Odds)...\n";
    $sync->sync3Hours();
    echo "3-Hours Sync Completed.\n";
} catch (\Throwable $e) {
    echo "3-Hours Sync Error: " . $e->getMessage() . "\n";
}

<?php
// cron_weekly.php - Eseguito ogni settimana
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;

$sync = new SyncController();
try {
    echo "Starting Weekly Sync...\n";
    $sync->syncWeekly();
    echo "Weekly Sync Completed.\n";
} catch (\Throwable $e) {
    echo "Weekly Sync Error: " . $e->getMessage() . "\n";
}

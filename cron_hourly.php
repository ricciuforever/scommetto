<?php
// cron_hourly.php - Eseguito ogni ora
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;

$sync = new SyncController();
try {
    echo "Starting Hourly Sync...\n";
    $sync->syncHourly();
    echo "Hourly Sync Completed.\n";
} catch (\Throwable $e) {
    echo "Hourly Sync Error: " . $e->getMessage() . "\n";
}

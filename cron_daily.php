<?php
// cron_daily.php - Eseguito ogni giorno
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;

$sync = new SyncController();
try {
    echo "Starting Daily Sync...\n";
    $sync->syncDaily();
    echo "Daily Sync Completed.\n";
} catch (\Throwable $e) {
    echo "Daily Sync Error: " . $e->getMessage() . "\n";
}

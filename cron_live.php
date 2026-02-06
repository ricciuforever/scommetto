<?php
// cron_live.php - Eseguito ogni minuto
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;

$sync = new SyncController();
try {
    echo "Starting Live Sync...\n";
    $sync->syncLive();
    echo "Live Sync Completed.\n";
} catch (\Throwable $e) {
    echo "Live Sync Error: " . $e->getMessage() . "\n";
}

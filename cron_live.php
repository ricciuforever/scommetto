<?php
// cron_live.php - Eseguito ogni minuto
require_once __DIR__ . '/bootstrap.php';
use App\Controllers\SyncController;
use App\GiaNik\Controllers\GiaNikController;

// 1. Standard Live Sync (DISABLED - Focused on GiaNik)
/*
$sync = new SyncController();
try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting Standard Live Sync...\n";
    $sync->syncLive();
    echo "Standard Live Sync Completed.\n";
} catch (\Throwable $e) {
    echo "Standard Live Sync Error: " . $e->getMessage() . "\n";
}
*/

// 2. GiaNik Auto-Process
$gianik = new GiaNikController();
try {
    // Get mode for logging
    $db = \App\GiaNik\GiaNikDatabase::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
    $stmt->execute();
    $mode = strtoupper($stmt->fetchColumn() ?: 'VIRTUAL');

    echo "[" . date('Y-m-d H:i:s') . "] Starting GiaNik Auto-Process ($mode MODE)...\n";
    $gianik->autoProcess();
    echo "\nGiaNik Auto-Process Completed.\n";
} catch (\Throwable $e) {
    echo "GiaNik Auto-Process Error: " . $e->getMessage() . "\n";
}

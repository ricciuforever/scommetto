<?php
// run_sync.php
require __DIR__ . '/vendor/autoload.php';

use App\Controllers\SyncController;

echo "Running Sync...\n";
try {
    (new SyncController())->syncLive();
    echo "\nSync Complete.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

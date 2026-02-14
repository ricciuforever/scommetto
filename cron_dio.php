<?php
/**
 * cron_dio.php
 * Automated scanning for Dio (Quantum Trader)
 * Runs every minute to identify trading opportunities across Betfair.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Dio\Controllers\DioQuantumController;
use App\Dio\Services\SettlementService;
use App\Dio\Controllers\BrainController;

// Ensure this script can only be run via CLI or with a specific token if via web
if (PHP_SAPI !== 'cli') {
    die("This script must be run via CLI.");
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Dio Quantum Scan...\n";
echo "[" . date('Y-m-d H:i:s') . "] Starting Dio Quantum Cycle...\n";

try {
    // 1. Settle pending bets
    $settlement = new SettlementService();
    $settled = $settlement->settlePendingBets();
    echo "[" . date('Y-m-d H:i:s') . "] Settled $settled bets.\n";

    // 2. Learn from settled bets (RAG Brain)
    $brain = new BrainController();
    $learned = $brain->learn();
    echo "[" . date('Y-m-d H:i:s') . "] Brain learning results: $learned\n";

    // 3. Scan and Trade
    $dio = new DioQuantumController();
    $dio->scanAndTrade();
    echo "[" . date('Y-m-d H:i:s') . "] Scan completed successfully.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/logs/dio_cron_error.log', date('[Y-m-d H:i:s] ') . $e->getMessage() . PHP_EOL, FILE_APPEND);
}

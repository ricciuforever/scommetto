<?php
// import_history.php

// Define base path (CURRENT DIR IS ROOT)
define('BASE_PATH', __DIR__ . '/');

require_once BASE_PATH . 'bootstrap.php';

use App\Services\BetfairService;
use App\Dio\DioDatabase;

echo "<pre>";
echo "--- STARTING HISTORY IMPORT (BROWSER MODE - ROOT) ---\n";

$bf = new BetfairService();
$db = DioDatabase::getInstance()->getConnection();

// 1. Force Auth / Check Session
echo "Checking Session...\n";
$token = $bf->authenticate();
if (!$token) {
    echo "Session invalid/missing. Attempting explicit login...\n";
    // We expect authenticate() to try login. Check logs if this fails.
    echo "Login failed. Please check logs/betfair_debug.log.\n";
} else {
    echo "Session Token: " . substr($token, 0, 10) . "...\n";
}

// 2. Fetch History (No Date Filter)
echo "\nFetching ALL Cleared Orders (No Date Filter)...\n";

$allOrders = [];
$fromRecord = 0;
$maxPages = 20; // Safety limit

while ($maxPages-- > 0) {
    echo "Fetching page starting at $fromRecord...\n";
    // PASSING null for startDate to get everything
    $res = $bf->getClearedOrders(false, null, $fromRecord);

    if (isset($res['error'])) {
        echo "API Error: " . json_encode($res['error']) . "\n";
        // If INVALID_SESSION, we must stop
        if (($res['error']['code'] ?? '') == -32099 || ($res['error']['message'] ?? '') == 'INVALID_SESSION_INFORMATION') {
            echo "CRITICAL: Session Invalid. Please restart script.\n";
            // Optional: $bf->clearPersistentToken();
            break;
        }
        break;
    }

    $orders = $res['clearedOrders'] ?? [];
    if (empty($orders))
        break;

    $allOrders = array_merge($allOrders, $orders);

    if (!$res['moreAvailable'])
        break;
    $fromRecord += count($orders);

    // Sleep to avoid rate limits
    usleep(500000);
    flush();
}

echo "Total Orders Fetched: " . count($allOrders) . "\n";

if (empty($allOrders)) {
    echo "No history found. Aborting overwrite.\n";
} else {
    // 3. Backup & Truncate
    echo "Backing up 'bets' to 'bets_backup'...\n";
    $db->exec("DROP TABLE IF EXISTS bets_backup");
    $db->exec("CREATE TABLE bets_backup AS SELECT * FROM bets");

    echo "Truncating 'bets' table...\n";
    $db->exec("DELETE FROM bets");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='bets'");

    // 4. Insert Data
    echo "Inserting imported bets...\n";
    $stmt = $db->prepare("INSERT INTO bets (
        market_id, market_name, event_name, sport, selection_id, runner_name, 
        odds, stake, status, type, betfair_id, motivation, profit, settled_at, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?, 'Imported History', ?, ?, ?
    )");

    $acceptedCount = 0;
    $totalPnL = 0;

    // Sort by settledDate ASC
    usort($allOrders, function ($a, $b) {
        return strtotime($a['settledDate']) - strtotime($b['settledDate']);
    });

    foreach ($allOrders as $order) {
        if ($order['betStatus'] !== 'SETTLED')
            continue;

        $profit = $order['profit'] ?? 0;

        $status = 'void';
        if ($profit > 0)
            $status = 'won';
        if ($profit < 0)
            $status = 'lost';

        $odds = $order['priceMatched'] ?? 0;
        $stake = $order['sizeSettled'] ?? 0;

        // Metadata
        $marketId = $order['marketId'];
        $marketName = $order['itemDescription']['marketDesc'] ?? 'Unknown Market';
        $eventName = $order['itemDescription']['eventDesc'] ?? 'Unknown Event';
        $runnerName = $order['itemDescription']['runnerDesc'] ?? 'Unknown Runner';
        $selectionId = $order['selectionId'];
        $sport = $order['itemDescription']['eventTypeDesc'] ?? 'Soccer';

        $betId = $order['betId'];
        $settledAt = date('Y-m-d H:i:s', strtotime($order['settledDate']));
        $placedAt = date('Y-m-d H:i:s', strtotime($order['placedDate']));

        $stmt->execute([
            $marketId,
            $marketName,
            $eventName,
            $sport,
            $selectionId,
            $runnerName,
            $odds,
            $stake,
            $status,
            $betId,
            $profit,
            $settledAt,
            $placedAt
        ]);

        $totalPnL += $profit;
        $acceptedCount++;
    }

    echo "Successfully imported $acceptedCount bets.\n";
    echo "Total P&L from History: " . number_format($totalPnL, 2) . " EUR\n";

    // 5. Align System Balance (P&L Driven)
    $initial = 100.00;
    $newBalance = $initial + $totalPnL;

    echo "Updating System Balance: $initial (Base) + $totalPnL (PnL) = $newBalance\n";

    $upd = $db->prepare("UPDATE system_state SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'virtual_balance'");
    $upd->execute([number_format($newBalance, 2, '.', '')]);

    // Reset mode to real
    $db->exec("UPDATE system_state SET value = 'real', updated_at = CURRENT_TIMESTAMP WHERE key = 'operational_mode'");
}

echo "--- IMPORT COMPLETED ---\n";
echo "</pre>";
?>
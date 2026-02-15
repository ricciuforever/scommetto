<?php
// import_history.php

// Define base path (CURRENT DIR IS ROOT)
define('BASE_PATH', __DIR__ . '/');

require_once BASE_PATH . 'bootstrap.php';

use App\Services\BetfairService;
use App\Dio\DioDatabase;
use App\Config\Config;

echo "<pre>";
echo "--- HISTORY IMPORT DIAGNOSTIC TOOL ---\n";
echo "--- STARTING AUTH DEBUG ---\n";

// 1. Environment Checks
$dataPath = Config::DATA_PATH;
echo "Data Path: $dataPath\n";
echo "Data Dir Exists: " . (is_dir($dataPath) ? "YES" : "NO") . "\n";
echo "Data Dir Writable: " . (is_writable($dataPath) ? "YES" : "NO") . "\n";

$certPath = Config::get('BETFAIR_CERT_PATH');
$keyPath = Config::get('BETFAIR_KEY_PATH');
echo "Cert Path: $certPath (" . (file_exists($certPath) ? "FOUND" : "MISSING") . ")\n";
echo "Key Path: $keyPath (" . (file_exists($keyPath) ? "FOUND" : "MISSING") . ")\n";

// 2. Explicit Service Login Debug
$bf = new BetfairService();

echo "\nAttempting BetfairService->authenticate()...\n";
// Temporarily enable debug logging to screen if possible, or just read the result
$token = $bf->authenticate();

if (!$token) {
    echo "Authenticate() returned FALSE/NULL.\n";
    echo "Check logs/betfair_debug.log for details.\n";

    // Try to read the last lines of the log
    $logFile = BASE_PATH . 'logs/betfair_debug.log';
    if (file_exists($logFile)) {
        echo "\n--- TAIL OF BETFAIR DEBUG LOG ---\n";
        $lines = file($logFile);
        $tail = array_slice($lines, -20);
        foreach ($tail as $line) {
            echo htmlspecialchars($line);
        }
        echo "--- END LOG ---\n";
    } else {
        echo "Log file not found at $logFile\n";
    }

    die("\nCRITICAL: Authentication failed. Cannot proceed with import.\n");
} else {
    echo "Authentication SUCCESS! Token: " . substr($token, 0, 10) . "...\n";
}

// 3. Import Logic (Only if Auth Success)
echo "\n--- STARTING IMPORT LOGIC ---\n";
$db = DioDatabase::getInstance()->getConnection();

echo "\nFetching ALL Cleared Orders (No Date Filter)...\n";
$allOrders = [];
$fromRecord = 0;
$maxPages = 20;

while ($maxPages-- > 0) {
    echo "Fetching page starting at $fromRecord... ";
    $res = $bf->getClearedOrders(false, null, $fromRecord);

    if (isset($res['error'])) {
        echo "FAILED: " . json_encode($res['error']) . "\n";
        break;
    }

    $orders = $res['clearedOrders'] ?? [];
    $count = count($orders);
    echo "Found $count orders.\n";

    if ($count === 0)
        break;

    $allOrders = array_merge($allOrders, $orders);

    if (!$res['moreAvailable'])
        break;
    $fromRecord += $count;

    usleep(500000);
    flush();
}

echo "Total Orders Fetched: " . count($allOrders) . "\n";

if (empty($allOrders)) {
    echo "No history found. Aborting overwrite.\n";
} else {
    echo "Backing up 'bets' to 'bets_backup'...\n";
    $db->exec("DROP TABLE IF EXISTS bets_backup");
    $db->exec("CREATE TABLE bets_backup AS SELECT * FROM bets");

    echo "Truncating 'bets' table...\n";
    $db->exec("DELETE FROM bets");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='bets'");

    echo "Inserting imported bets...\n";
    $stmt = $db->prepare("INSERT INTO bets (
        market_id, market_name, event_name, sport, selection_id, runner_name, 
        odds, stake, status, type, betfair_id, motivation, profit, settled_at, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?, 'Imported History', ?, ?, ?
    )");

    $acceptedCount = 0;
    $totalPnL = 0;

    usort($allOrders, function ($a, $b) {
        return strtotime($a['settledDate']) - strtotime($b['settledDate']);
    });

    foreach ($allOrders as $order) {
        if ($order['betStatus'] !== 'SETTLED')
            continue;

        $profit = $order['profit'] ?? 0;
        $status = ($profit > 0) ? 'won' : (($profit < 0) ? 'lost' : 'void');

        $odds = $order['priceMatched'] ?? 0;
        $stake = $order['sizeSettled'] ?? 0;

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

    $initial = 100.00;
    $newBalance = $initial + $totalPnL;
    echo "Updating System Balance: $newBalance\n";

    $upd = $db->prepare("UPDATE system_state SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'virtual_balance'");
    $upd->execute([number_format($newBalance, 2, '.', '')]);

    $db->exec("UPDATE system_state SET value = 'real', updated_at = CURRENT_TIMESTAMP WHERE key = 'operational_mode'");
}

echo "--- IMPORT COMPLETED ---\n";
echo "</pre>";
?>
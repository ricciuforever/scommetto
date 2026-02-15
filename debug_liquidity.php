<?php
// debug_liquidity.php
// Debug script to check Betfair API response for liquidity issues

require_once __DIR__ . '/bootstrap.php';

use App\Dio\Controllers\DioQuantumController;
use App\Services\BetfairService;
use App\Dio\DioDatabase;

// Initialize Dio context
$db = DioDatabase::getInstance()->getConnection();
$config = $db->query("SELECT key, value FROM system_state WHERE key LIKE 'BETFAIR_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$overrides = array_filter($config);

// Override login to use Dio agent specific session
$bf = new BetfairService($overrides, 'dio');

// 1. Get Live Events (Soccer) to find a candidate
echo "--- Searching for Live Soccer Events ---\n";
$eventsRes = $bf->getLiveEvents([1]); // Soccer
$events = $eventsRes['result'] ?? [];

if (empty($events)) {
    die("No live soccer events found.\n");
}

// Find a likely candidate (e.g. Spezia or similar, otherwise pick first)
$targetEvent = null;
foreach ($events as $e) {
    if (stripos($e['event']['name'], 'Spezia') !== false || stripos($e['event']['name'], 'Frosinone') !== false) {
        $targetEvent = $e;
        break;
    }
}

if (!$targetEvent) {
    echo "Spezia not found. Picking first available: " . $events[0]['event']['name'] . "\n";
    $targetEvent = $events[0];
} else {
    echo "Target Found: " . $targetEvent['event']['name'] . "\n";
}

$eventId = $targetEvent['event']['id'];
$eventName = $targetEvent['event']['name'];
echo "Selected Event: $eventName (ID: $eventId)\n";

// 2. Get Market Catalogue (MATCH_ODDS)
echo "--- Fetching Market Catalogue (MATCH_ODDS) ---\n";
// Request specific market types to verify we get the MAIN one
$catRes = $bf->getMarketCatalogues([$eventId], 10, ['MATCH_ODDS'], 'FIRST_TO_START');
$catalogues = $catRes['result'] ?? [];

if (empty($catalogues)) {
    die("No MATCH_ODDS market found for this event.\n");
}

$targetMarket = $catalogues[0]; // Assuming first match is main
$marketId = $targetMarket['marketId'];
$marketName = $targetMarket['marketName'];
echo "Market Found: $marketName (ID: $marketId)\n";

// 3. Get Market Book (Liquidity Check)
echo "--- Fetching Market Book (Price Projection) ---\n";
// This mimics the call in DioQuantumController::scanAndTrade
$bookRes = $bf->getMarketBooks([$marketId]);
$book = $bookRes['result'][0] ?? null;

if (!$book) {
    die("Failed to fetch Market Book.\n");
}

echo "Status: " . ($book['status'] ?? 'Unknown') . "\n";
echo "InPlay: " . (($book['inplay'] ?? false) ? 'YES' : 'NO') . "\n";
echo "Total Matched (Liquidity): " . number_format($book['totalMatched'] ?? 0, 2) . "€\n";
echo "Total Available (Back): " . number_format($book['totalAvailable'] ?? 0, 2) . "€\n";

// Check Runners details if available
if (!empty($book['runners'])) {
    foreach ($book['runners'] as $runner) {
        echo "  - Runner ID: " . $runner['selectionId'] . "\n";
        echo "    Last Price Traded: " . ($runner['lastPriceTraded'] ?? 'N/A') . "\n";
        echo "    Total Matched (Runner Level): " . ($runner['totalMatched'] ?? 'N/A') . "\n";
    }
}

echo "\n--- RAW DUMP of TotalMatched ---\n";
var_dump($book['totalMatched']);

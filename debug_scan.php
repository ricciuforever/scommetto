<?php
require __DIR__ . '/bootstrap.php';
use App\Services\BetfairService;
use App\Dio\DioDatabase;

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- Debug Scan Analysis ---\n";

$bf = new BetfairService();
if (!$bf->isConfigured()) {
    die("Betfair Service NOT Configured!\n");
}

// 1. Get Event Types
echo "1. Fetching Event Types (InPlay Only)...\n";
$eventTypesRes = $bf->getEventTypes(['inPlayOnly' => true]);
$eventTypes = $eventTypesRes['result'] ?? [];
echo "Found " . count($eventTypes) . " sports.\n";

if (empty($eventTypes)) {
    die("No sports found. Check API or Time of day.\n");
}

foreach ($eventTypes as $et) {
    $sportId = $et['eventType']['id'];
    $sportName = $et['eventType']['name'];
    echo " > Sport: $sportName (ID: $sportId)\n";

    // 2. Get Live Events
    $liveEventsRes = $bf->getLiveEvents([$sportId]);
    $events = $liveEventsRes['result'] ?? [];
    echo "   - Live Events Found: " . count($events) . "\n";

    if (empty($events))
        continue;

    $eventIds = array_map(fn($e) => $e['event']['id'], $events);

    // 3. Get Market Catalogues
    echo "   - Fetching Catalogues for " . count($eventIds) . " events...\n";
    $cataloguesRes = $bf->getMarketCatalogues($eventIds, 5); // Limit to 5 for debug speed
    $catalogues = $cataloguesRes['result'] ?? [];
    echo "   - Catalogues Found: " . count($catalogues) . "\n";

    if (empty($catalogues))
        continue;

    $marketIds = array_map(fn($m) => $m['marketId'], $catalogues);

    // 4. Get Market Books (Prices)
    echo "   - Fetching Books for " . count($marketIds) . " markets...\n";
    $booksRes = $bf->getMarketBooks($marketIds);
    $books = $booksRes['result'] ?? [];

    foreach ($books as $book) {
        $mId = $book['marketId'];
        $totalMatched = $book['totalMatched'] ?? 0;
        $status = $book['status'] ?? 'UNKNOWN';
        echo "     [MKT: $mId] Status: $status | Matched: $totalMatched | InPlay: " . ($book['inplay'] ? 'YES' : 'NO') . "\n";
    }
}
echo "\nDone Debug Scan.\n";

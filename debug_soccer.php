<?php
require __DIR__ . '/bootstrap.php';
use App\Services\BetfairService;

$bf = new BetfairService([], 'dio');

echo "--- Soccer Debug ---\n";
$liveSoccer = $bf->getLiveEvents(["1"]);
$events = $liveSoccer['result'] ?? [];
echo "Live Soccer Events Found: " . count($events) . "\n";

if (!empty($events)) {
    $eventIds = array_map(fn($e) => $e['event']['id'], $events);
    foreach($events as $e) {
        echo " - " . $e['event']['name'] . " (ID: " . $e['event']['id'] . ")\n";
    }

    $cataloguesRes = $bf->getMarketCatalogues($eventIds, 100);
    $catalogues = $cataloguesRes['result'] ?? [];
    echo "Catalogues Found for Soccer: " . count($catalogues) . "\n";

    foreach(array_slice($catalogues, 0, 10) as $c) {
        echo "   > Market: " . $c['marketName'] . " | Event: " . $c['event']['name'] . "\n";
    }
}

echo "\n--- Tennis Debug ---\n";
$liveTennis = $bf->getLiveEvents(["2"]);
$eventsT = $liveTennis['result'] ?? [];
echo "Live Tennis Events Found: " . count($eventsT) . "\n";

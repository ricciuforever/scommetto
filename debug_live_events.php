<?php
require_once __DIR__ . '/bootstrap.php';

use App\Services\BetfairService;

$bf = new BetfairService();

echo "Testing Betfair Live Events (IT)...\n";

if (!$bf->isConfigured()) {
    die("Betfair not configured.\n");
}

// 1. Get Event Types
echo "Fetching Event Types...\n";
$types = $bf->getEventTypes();
if (empty($types['result'])) {
    die("Error fetching event types: " . json_encode($types) . "\n");
}

$sportIds = array_map(fn($t) => $t['eventType']['id'], $types['result']);
echo "Found " . count($sportIds) . " sports.\n";

// 2. Get Live Events
echo "Fetching Live Events...\n";
$events = $bf->getLiveEvents($sportIds);

if (empty($events['result'])) {
    echo "No live events found.\n";
} else {
    echo "Found " . count($events['result']) . " live events.\n";
    foreach ($events['result'] as $e) {
        echo "- [" . $e['event']['countryCode'] . "] " . $e['event']['name'] . " (" . $e['competition']['name'] . ")\n";
    }
}

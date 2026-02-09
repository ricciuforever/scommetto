<?php
require_once 'bootstrap.php';
use App\Services\FootballApiService;

$api = new FootballApiService();
$result = $api->fetchLiveOdds();

echo "Response count: " . (isset($result['response']) ? count($result['response']) : '0') . "\n";
if (!empty($result['response'])) {
    $bkCount = 0;
    foreach ($result['response'] as $match) {
        if (!empty($match['bookmakers'])) {
            $bkCount += count($match['bookmakers']);
            foreach ($match['bookmakers'] as $bk) {
                echo "Match: " . $match['fixture']['id'] . " - Bookmaker: " . $bk['name'] . " (" . $bk['id'] . ")\n";
            }
        }
    }
    echo "Total bookmaker entries found: $bkCount\n";
} else {
    echo "No response or empty response.\n";
    print_r($result);
}

<?php
// test_active_bookmakers.php
require_once 'bootstrap.php';
use App\Controllers\OddsController;

// Mocking $_SERVER for index.php or just call the controller directly
ob_start();
$ctrl = new OddsController();
$ctrl->activeBookmakers();
$response = ob_get_clean();

echo "Response: " . $response . "\n";
$data = json_decode($response, true);

if (!empty($data['response'])) {
    echo "SUCCESS: Found " . count($data['response']) . " bookmakers.\n";
    foreach ($data['response'] as $bk) {
        echo " - " . $bk['name'] . " (ID: " . $bk['id'] . ")\n";
    }
} else {
    echo "FAILURE: Bookmaker list is empty.\n";
}

<?php
// test_db.php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "Connection OK\n";

    // Test analyses insert
    $stmt = $db->prepare("INSERT INTO analyses (fixture_id, last_checked, prediction_raw) VALUES (?, CURRENT_TIMESTAMP, ?) ON DUPLICATE KEY UPDATE last_checked=CURRENT_TIMESTAMP, prediction_raw=VALUES(prediction_raw)");
    $res = $stmt->execute([999999, "Test prediction"]);

    if ($res) {
        echo "Insert to analyses OK\n";
    } else {
        echo "Insert to analyses FAILED\n";
    }

    // Test select
    $stmt = $db->query("SELECT * FROM analyses WHERE fixture_id = 999999");
    $row = $stmt->fetch();
    if ($row) {
        echo "Select OK: " . $row['prediction_raw'] . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

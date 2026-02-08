<?php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    echo "Modifying bets table schema...\n";

    // Change fixture_id to VARCHAR to support Betfair Market IDs
    $sql = "ALTER TABLE bets MODIFY COLUMN fixture_id VARCHAR(100) NOT NULL";
    $db->exec($sql);
    echo "Column fixture_id modified to VARCHAR(100).\n";

    // Add betfair_id column if not exists
    $stmt = $db->query("SHOW COLUMNS FROM bets LIKE 'betfair_id'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE bets ADD COLUMN betfair_id VARCHAR(100) DEFAULT NULL";
        $db->exec($sql);
        echo "Column betfair_id added.\n";
    }

    // Add bookmaker_id column if not exists
    $stmt = $db->query("SHOW COLUMNS FROM bets LIKE 'bookmaker_id'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE bets ADD COLUMN bookmaker_id INT(11) DEFAULT NULL";
        $db->exec($sql);
        echo "Column bookmaker_id added.\n";
    }

    // Add bookmaker_name column if not exists
    $stmt = $db->query("SHOW COLUMNS FROM bets LIKE 'bookmaker_name'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE bets ADD COLUMN bookmaker_name VARCHAR(100) DEFAULT NULL";
        $db->exec($sql);
        echo "Column bookmaker_name added.\n";
    }

    echo "Schema update completed successfully.\n";

} catch (\Throwable $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}

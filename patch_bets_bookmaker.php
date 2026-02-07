<?php
require_once __DIR__ . '/bootstrap.php';
$db = \App\Services\Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE bets ADD COLUMN bookmaker_id INT NULL AFTER fixture_id");
    $db->exec("ALTER TABLE bets ADD COLUMN bookmaker_name VARCHAR(100) NULL AFTER bookmaker_id");
    echo "Columns added successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

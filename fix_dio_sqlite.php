<?php
require_once __DIR__ . '/bootstrap.php';
use App\Dio\DioDatabase;

try {
    $db = DioDatabase::getInstance()->getConnection();
    echo "Checking schema for Dio DB...\n";

    // Check if score exists in bets table
    $stmt = $db->query("PRAGMA table_info(bets)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasScore = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'score') {
            $hasScore = true;
            break;
        }
    }

    if (!$hasScore) {
        echo "Adding 'score' column to 'bets' table...\n";
        $db->exec("ALTER TABLE bets ADD COLUMN score TEXT DEFAULT NULL");
        echo "Column 'score' added successfully.\n";
    } else {
        echo "Column 'score' already exists in 'bets' table.\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

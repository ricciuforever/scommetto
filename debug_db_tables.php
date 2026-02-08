<?php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";

    foreach (['league_seasons', 'teams', 'team_leagues', 'venues', 'api_usage'] as $table) {
        if (in_array($table, $tables)) {
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "$table: $count rows\n";
        } else {
            echo "$table: MISSING\n";
        }
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

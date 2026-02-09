<?php
// install_career.php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "🚀 Installing Player Career schema...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `player_career` (
      `player_id` INT,
      `team_id` INT,
      `seasons_json` LONGTEXT COMMENT 'Array of seasons played',
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`player_id`, `team_id`),
      FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    echo "✅ Table `player_career` created successfully.\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

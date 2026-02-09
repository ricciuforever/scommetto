<?php
// install_transfers.php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `player_transfers` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `player_id` INT NOT NULL,
          `update_date` DATETIME,
          `transfers_json` JSON,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `unique_player` (`player_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    echo "Tabella player_transfers creata con successo.\n";

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

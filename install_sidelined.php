<?php
// install_sidelined.php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `player_sidelined` (
          `player_id` INT NOT NULL,
          `sidelined_json` JSON,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`player_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    echo "Tabella player_sidelined creata con successo.\n";

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

<?php
// install_live_items.php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    // Create live_bet_types
    $sqlBets = "CREATE TABLE IF NOT EXISTS `live_bet_types` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlBets);
    echo "Tabella live_bet_types creata/aggiornata.\n";

    // Create/Ensure live_odds (referenced by LiveOdds model)
    $sqlLiveOdds = "CREATE TABLE IF NOT EXISTS `live_odds` (
          `fixture_id` INT PRIMARY KEY,
          `odds_json` JSON,
          `status_json` JSON,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlLiveOdds);
    echo "Tabella live_odds creata/aggiornata.\n";

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

<?php
// install_odds.php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    // Create bookmakers
    $sqlBookmakers = "CREATE TABLE IF NOT EXISTS `bookmakers` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlBookmakers);
    echo "Tabella bookmakers creata/aggiornata.\n";

    // Create bet_types
    $sqlBets = "CREATE TABLE IF NOT EXISTS `bet_types` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `type` ENUM('pre-match', 'live') DEFAULT 'pre-match',
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlBets);
    echo "Tabella bet_types creata/aggiornata.\n";

    // Create fixture_odds
    $sqlFixtureOdds = "CREATE TABLE IF NOT EXISTS `fixture_odds` (
          `fixture_id` INT,
          `bookmaker_id` INT,
          `bet_id` INT,
          `odds_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`),
          INDEX (`fixture_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlFixtureOdds);
    echo "Tabella fixture_odds creata/aggiornata.\n";

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

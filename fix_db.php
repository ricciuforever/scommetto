<?php
// fix_db.php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
  $db = Database::getInstance()->getConnection();

  echo "Controllo tabelle mancanti...\n";

  $sql = "CREATE TABLE IF NOT EXISTS `countries` (
      `name` VARCHAR(100) PRIMARY KEY,
      `code` VARCHAR(10),
      `flag` VARCHAR(255),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $db->exec($sql);
  echo "✅ Tabella 'countries' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `seasons` (
      `year` INT PRIMARY KEY,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'seasons' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `leagues` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `type` VARCHAR(50),
      `logo` VARCHAR(255),
      `country_name` VARCHAR(100),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`country_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'leagues' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `league_seasons` (
      `league_id` INT,
      `year` INT,
      `is_current` BOOLEAN DEFAULT FALSE,
      PRIMARY KEY (`league_id`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'league_seasons' verificata.\n";

  $sql = "CREATE TABLE IF NOT EXISTS `predictions` (
      `fixture_id` INT PRIMARY KEY,
      `advice` TEXT,
      `comparison_json` TEXT,
      `percent_json` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

  $db->exec($sql);
  echo "✅ Tabella 'predictions' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixtures` (
      `id` INT PRIMARY KEY,
      `league_id` INT,
      `team_home_id` INT,
      `team_away_id` INT,
      `date` DATETIME,
      `status` VARCHAR(20),
      `score_home` INT,
      `score_away` INT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixtures' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `team_stats` (
      `team_id` INT,
      `league_id` INT,
      `season` INT,
      `played` INT,
      `wins` INT,
      `draws` INT,
      `losses` INT,
      `goals_for` INT,
      `goals_against` INT,
      `clean_sheets` INT,
      `failed_to_score` INT,
      `avg_goals_for` DECIMAL(4,2),
      `avg_goals_against` DECIMAL(4,2),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`team_id`, `league_id`, `season`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'team_stats' verificata.\n";

  try {
    $db->exec("ALTER TABLE `bets` ADD COLUMN `confidence` INT DEFAULT 0 AFTER `urgency`;");
    echo "✅ Colonna 'confidence' aggiunta a 'bets'.\n";
  } catch (\Exception $e) { /* ignore if already exists */
  }

  echo "\nEsecuzione completata. Ora puoi eliminare questo file.\n";

} catch (\Exception $e) {
  echo "❌ Errore durante l'aggiornamento del database: " . $e->getMessage() . "\n";
}

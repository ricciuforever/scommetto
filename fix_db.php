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
      `coverage_json` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`country_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'leagues' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `league_seasons` (
      `league_id` INT,
      `year` INT,
      `is_current` BOOLEAN DEFAULT FALSE,
      `start_date` DATE,
      `end_date` DATE,
      PRIMARY KEY (`league_id`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'league_seasons' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_injuries` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `fixture_id` INT,
      `team_id` INT,
      `player_id` INT,
      `player_name` VARCHAR(100),
      `type` VARCHAR(50),
      `reason` VARCHAR(255),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`fixture_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_injuries' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `coaches` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `firstname` VARCHAR(100),
      `lastname` VARCHAR(100),
      `age` INT,
      `birth_date` DATE,
      `birth_country` VARCHAR(100),
      `nationality` VARCHAR(100),
      `team_id` INT,
      `photo` VARCHAR(255),
      `career_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'coaches' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `api_usage` (
      `id` INT PRIMARY KEY DEFAULT 1,
      `requests_used` INT DEFAULT 0,
      `requests_remaining` INT DEFAULT 7500,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'api_usage' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `analyses` (
      `fixture_id` INT PRIMARY KEY,
      `last_checked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `prediction_raw` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'analyses' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `player_seasons` (
      `year` INT PRIMARY KEY,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'player_seasons' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `predictions` (
      `fixture_id` INT PRIMARY KEY,
      `advice` TEXT,
      `comparison_json` LONGTEXT,
      `percent_json` TEXT,
      `predictions_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'predictions' verificata.\n";

  $sql = "CREATE TABLE IF NOT EXISTS `rounds` (
      `league_id` INT,
      `season` INT,
      `round_name` VARCHAR(100),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`league_id`, `season`, `round_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $db->exec($sql);
  echo "✅ Tabella 'rounds' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixtures` (
      `id` INT PRIMARY KEY,
      `league_id` INT,
      `round` VARCHAR(100),
      `team_home_id` INT,
      `team_away_id` INT,
      `date` DATETIME,
      `status_short` VARCHAR(10),
      `status_long` VARCHAR(50),
      `elapsed` INT,
      `score_home` INT,
      `score_away` INT,
      `venue_id` INT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixtures' verificata.\n";
  $db->exec("CREATE TABLE IF NOT EXISTS `bets` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `fixture_id` INT NOT NULL,
      `match_name` VARCHAR(255) NOT NULL,
      `advice` TEXT,
      `market` VARCHAR(100),
      `odds` DECIMAL(8,2),
      `stake` DECIMAL(8,2),
      `urgency` VARCHAR(50),
      `status` ENUM('pending','won','lost','void') DEFAULT 'pending',
      `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `result` VARCHAR(50)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'bets' verificata.\n";


  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_statistics` (
      `fixture_id` INT,
      `team_id` INT,
      `stats_json` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`fixture_id`, `team_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_statistics' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `h2h_records` (
      `team1_id` INT,
      `team2_id` INT,
      `h2h_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`team1_id`, `team2_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'h2h_records' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_events` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `fixture_id` INT,
      `team_id` INT,
      `player_id` INT,
      `assist_id` INT,
      `time_elapsed` INT,
      `time_extra` INT,
      `type` VARCHAR(50),
      `detail` VARCHAR(100),
      `comments` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`fixture_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_events' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_lineups` (
      `fixture_id` INT,
      `team_id` INT,
      `formation` VARCHAR(20),
      `coach_id` INT,
      `start_xi_json` LONGTEXT,
      `substitutes_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`fixture_id`, `team_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_lineups' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_player_stats` (
      `fixture_id` INT,
      `team_id` INT,
      `player_id` INT,
      `stats_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`fixture_id`, `team_id`, `player_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_player_stats' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `players` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `firstname` VARCHAR(100),
      `lastname` VARCHAR(100),
      `age` INT,
      `birth_date` DATE,
      `birth_place` VARCHAR(100),
      `birth_country` VARCHAR(100),
      `nationality` VARCHAR(100),
      `height` VARCHAR(20),
      `weight` VARCHAR(20),
      `injured` BOOLEAN DEFAULT FALSE,
      `photo` VARCHAR(255),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'players' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `player_statistics` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `player_id` INT,
      `team_id` INT,
      `league_id` INT,
      `season` INT,
      `stats_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `player_team_league_season` (`player_id`, `team_id`, `league_id`, `season`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'player_statistics' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `squads` (
      `team_id` INT,
      `player_id` INT,
      `position` VARCHAR(50),
      `number` INT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`team_id`, `player_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'squads' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `top_stats` (
      `league_id` INT,
      `season` INT,
      `type` ENUM('scorers', 'assists', 'yellow_cards', 'red_cards'),
      `stats_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`league_id`, `season`, `type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'top_stats' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `venues` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(255),
      `address` VARCHAR(255),
      `city` VARCHAR(100),
      `capacity` INT,
      `surface` VARCHAR(50),
      `image` VARCHAR(255),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'venues' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `teams` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `code` VARCHAR(10),
      `country` VARCHAR(100),
      `founded` INT,
      `national` BOOLEAN DEFAULT FALSE,
      `logo` VARCHAR(255),
      `venue_id` INT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'teams' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `team_leagues` (
      `team_id` INT,
      `league_id` INT,
      `season` INT,
      PRIMARY KEY (`team_id`, `league_id`, `season`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✅ Tabella 'team_leagues' verificata.\n";

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
      `full_stats_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`team_id`, `league_id`, `season`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'team_stats' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `transfers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `player_id` INT,
      `transfer_date` DATE,
      `type` VARCHAR(100),
      `team_out_id` INT,
      `team_in_id` INT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`player_id`),
      INDEX (`team_in_id`),
      INDEX (`team_out_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'transfers' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `trophies` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `player_id` INT DEFAULT NULL,
      `coach_id` INT DEFAULT NULL,
      `league` VARCHAR(100),
      `country` VARCHAR(100),
      `season` VARCHAR(20),
      `place` VARCHAR(50),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`player_id`),
      INDEX (`coach_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'trophies' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `sidelined` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `player_id` INT DEFAULT NULL,
      `coach_id` INT DEFAULT NULL,
      `type` VARCHAR(100),
      `start_date` DATE,
      `end_date` DATE,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`player_id`),
      INDEX (`coach_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'sidelined' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `live_odds` (
      `fixture_id` INT PRIMARY KEY,
      `odds_json` LONGTEXT,
      `status_json` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'live_odds' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `bookmakers` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'bookmakers' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `bet_types` (
      `id` INT PRIMARY KEY,
      `name` VARCHAR(100),
      `type` ENUM('pre-match', 'live') DEFAULT 'pre-match',
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'bet_types' verificata.\n";

  $db->exec("CREATE TABLE IF NOT EXISTS `fixture_odds` (
      `fixture_id` INT,
      `bookmaker_id` INT,
      `bet_id` INT,
      `odds_json` LONGTEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  echo "✅ Tabella 'fixture_odds' verificata.\n";


  // Patch Fixtures if missing columns
  $fixturesPatches = [
      "ALTER TABLE `fixtures` ADD COLUMN `round` VARCHAR(100) AFTER `league_id`;",
      "ALTER TABLE `fixtures` ADD COLUMN `status_short` VARCHAR(10) AFTER `date`;",
      "ALTER TABLE `fixtures` ADD COLUMN `status_long` VARCHAR(50) AFTER `status_short`;",
      "ALTER TABLE `fixtures` ADD COLUMN `elapsed` INT AFTER `status_long`;",
      "ALTER TABLE `fixtures` ADD COLUMN `venue_id` INT AFTER `score_away`;"
  ];

  foreach ($fixturesPatches as $patch) {
      try {
          $db->exec($patch);
          echo "✅ Patch fixtures eseguita: " . substr($patch, 0, 40) . "...\n";
      } catch (\Exception $e) { /* ignore if already exists */ }
  }

  try {
      $db->exec("UPDATE fixtures SET status_short = status WHERE status_short IS NULL AND status IS NOT NULL");
  } catch (\Exception $e) { /* ignore */ }

  try {
    $db->exec("ALTER TABLE `bets` ADD COLUMN `confidence` INT DEFAULT 0 AFTER `urgency`;");
    echo "✅ Colonna 'confidence' aggiunta a 'bets'.\n";
  } catch (\Exception $e) { /* ignore if already exists */
  }

  echo "\nEsecuzione completata. Ora puoi eliminare questo file.\n";

} catch (\Exception $e) {
  echo "❌ Errore durante l'aggiornamento del database: " . $e->getMessage() . "\n";
}

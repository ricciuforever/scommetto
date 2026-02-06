<?php
// fix_db.php - Scommetto DB Migration & Repair Script v5.0
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
  $db = Database::getInstance()->getConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  echo "🚀 Inizio riparazione e migrazione database...\n";

  // --- 1. CREAZIONE TABELLE BASE (Se non esistono) ---

  $tables = [
      "countries" => "CREATE TABLE IF NOT EXISTS `countries` (
          `name` VARCHAR(100) PRIMARY KEY,
          `code` VARCHAR(10),
          `flag` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "seasons" => "CREATE TABLE IF NOT EXISTS `seasons` (
          `year` INT PRIMARY KEY,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "leagues" => "CREATE TABLE IF NOT EXISTS `leagues` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `type` VARCHAR(50),
          `logo` VARCHAR(255),
          `country_name` VARCHAR(100),
          `coverage_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "league_seasons" => "CREATE TABLE IF NOT EXISTS `league_seasons` (
          `league_id` INT,
          `year` INT,
          `is_current` BOOLEAN DEFAULT FALSE,
          `start_date` DATE,
          `end_date` DATE,
          PRIMARY KEY (`league_id`, `year`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "teams" => "CREATE TABLE IF NOT EXISTS `teams` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `code` VARCHAR(10),
          `country` VARCHAR(100),
          `founded` INT,
          `national` BOOLEAN DEFAULT FALSE,
          `logo` VARCHAR(255),
          `venue_id` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "venues" => "CREATE TABLE IF NOT EXISTS `venues` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `address` VARCHAR(255),
          `city` VARCHAR(100),
          `capacity` INT,
          `surface` VARCHAR(50),
          `image` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "standings" => "CREATE TABLE IF NOT EXISTS `standings` (
          `league_id` INT,
          `team_id` INT,
          `rank` INT,
          `points` INT,
          `goals_diff` INT,
          `form` VARCHAR(50),
          `group_name` VARCHAR(100),
          `description` TEXT,
          `played` INT,
          `win` INT,
          `draw` INT,
          `lose` INT,
          `goals_for` INT,
          `goals_against` INT,
          `home_stats_json` TEXT,
          `away_stats_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `team_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixtures" => "CREATE TABLE IF NOT EXISTS `fixtures` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "top_stats" => "CREATE TABLE IF NOT EXISTS `top_stats` (
          `league_id` INT,
          `season` INT,
          `type` ENUM('scorers', 'assists', 'yellow_cards', 'red_cards'),
          `stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `season`, `type`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "h2h_records" => "CREATE TABLE IF NOT EXISTS `h2h_records` (
          `team1_id` INT,
          `team2_id` INT,
          `h2h_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`team1_id`, `team2_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "bets" => "CREATE TABLE IF NOT EXISTS `bets` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `fixture_id` INT NOT NULL,
          `match_name` VARCHAR(255) NOT NULL,
          `advice` TEXT,
          `market` VARCHAR(100),
          `odds` DECIMAL(8,2),
          `stake` DECIMAL(8,2),
          `urgency` VARCHAR(50),
          `confidence` INT DEFAULT 0,
          `status` ENUM('pending','won','lost','void') DEFAULT 'pending',
          `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `result` VARCHAR(50),
          INDEX (`fixture_id`),
          INDEX (`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "api_usage" => "CREATE TABLE IF NOT EXISTS `api_usage` (
          `id` INT PRIMARY KEY DEFAULT 1,
          `requests_used` INT DEFAULT 0,
          `requests_remaining` INT DEFAULT 7500,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "analyses" => "CREATE TABLE IF NOT EXISTS `analyses` (
          `fixture_id` INT PRIMARY KEY,
          `last_checked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `prediction_raw` TEXT
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "predictions" => "CREATE TABLE IF NOT EXISTS `predictions` (
          `fixture_id` INT PRIMARY KEY,
          `advice` TEXT,
          `comparison_json` LONGTEXT,
          `percent_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "players" => "CREATE TABLE IF NOT EXISTS `players` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "coaches" => "CREATE TABLE IF NOT EXISTS `coaches` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "squads" => "CREATE TABLE IF NOT EXISTS `squads` (
          `team_id` INT,
          `player_id` INT,
          `position` VARCHAR(50),
          `number` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `player_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  ];

  foreach ($tables as $name => $sql) {
      $db->exec($sql);
      echo "✅ Tabella '$name' verificata/creata.\n";
  }

  // --- 2. PATCHING COLONNE MANCANTI ---

  $patches = [
      "leagues" => [
          "ALTER TABLE leagues ADD COLUMN type VARCHAR(50) AFTER name",
          "ALTER TABLE leagues ADD COLUMN country_name VARCHAR(100) AFTER logo",
          "ALTER TABLE leagues ADD COLUMN coverage_json TEXT AFTER country_name"
      ],
      "teams" => [
          "ALTER TABLE teams ADD COLUMN code VARCHAR(10) AFTER name",
          "ALTER TABLE teams ADD COLUMN country VARCHAR(100) AFTER code",
          "ALTER TABLE teams ADD COLUMN founded INT AFTER country",
          "ALTER TABLE teams ADD COLUMN national BOOLEAN DEFAULT 0 AFTER founded",
          "ALTER TABLE teams ADD COLUMN logo VARCHAR(255) AFTER national",
          "ALTER TABLE teams ADD COLUMN venue_id INT AFTER logo"
      ],
      "fixtures" => [
          "ALTER TABLE fixtures ADD COLUMN round VARCHAR(100) AFTER league_id",
          "ALTER TABLE fixtures ADD COLUMN status_short VARCHAR(10) AFTER date",
          "ALTER TABLE fixtures ADD COLUMN status_long VARCHAR(50) AFTER status_short",
          "ALTER TABLE fixtures ADD COLUMN elapsed INT AFTER status_long",
          "ALTER TABLE fixtures ADD COLUMN venue_id INT AFTER score_away"
      ],
      "standings" => [
          "ALTER TABLE standings ADD COLUMN group_name VARCHAR(100) AFTER form",
          "ALTER TABLE standings ADD COLUMN description TEXT AFTER group_name",
          "ALTER TABLE standings ADD COLUMN played INT AFTER description",
          "ALTER TABLE standings ADD COLUMN win INT AFTER played",
          "ALTER TABLE standings ADD COLUMN draw INT AFTER win",
          "ALTER TABLE standings ADD COLUMN lose INT AFTER draw",
          "ALTER TABLE standings ADD COLUMN goals_for INT AFTER lose",
          "ALTER TABLE standings ADD COLUMN goals_against INT AFTER goals_for",
          "ALTER TABLE standings ADD COLUMN home_stats_json TEXT AFTER goals_against",
          "ALTER TABLE standings ADD COLUMN away_stats_json TEXT AFTER home_stats_json"
      ],
      "bets" => [
          "ALTER TABLE bets ADD COLUMN confidence INT DEFAULT 0 AFTER urgency"
      ]
  ];

  foreach ($patches as $table => $sqlList) {
      foreach ($sqlList as $sql) {
          try {
              $db->exec($sql);
              echo "✅ Patch eseguita: " . substr($sql, 0, 50) . "...\n";
          } catch (\Exception $e) {
              // Ignora se la colonna esiste già
          }
      }
  }

  // Migrazione dati status -> status_short se necessario
  try {
      $db->exec("UPDATE fixtures SET status_short = status WHERE status_short IS NULL AND status IS NOT NULL");
      echo "✅ Migrazione fixtures status completata.\n";
  } catch (\Exception $e) {}

  echo "\n✨ Database sincronizzato con successo. Ora puoi eliminare questo file.\n";

} catch (\Throwable $e) {
  echo "❌ Errore critico: " . $e->getMessage() . "\n";
}

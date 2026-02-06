<?php
// fix_db.php - Scommetto DB Migration & Repair Script v8.4
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
  $db = Database::getInstance()->getConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  echo "🚀 Inizio riparazione e migrazione database (v8.4)...\n";

  // --- 1. CREAZIONE TABELLE BASE (Consolidate) ---

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
          `name` VARCHAR(255),
          `type` VARCHAR(50),
          `country` VARCHAR(100),
          `logo` VARCHAR(255),
          `country_name` VARCHAR(100),
          `coverage_json` TEXT,
          `season` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "league_seasons" => "CREATE TABLE IF NOT EXISTS `league_seasons` (
          `league_id` INT,
          `year` INT,
          `is_current` TINYINT(1) DEFAULT 0,
          `start_date` DATE,
          `end_date` DATE,
          PRIMARY KEY (`league_id`, `year`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "rounds" => "CREATE TABLE IF NOT EXISTS `rounds` (
          `league_id` INT,
          `season` INT,
          `round_name` VARCHAR(100),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `season`, `round_name`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "teams" => "CREATE TABLE IF NOT EXISTS `teams` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `code` VARCHAR(10),
          `logo` VARCHAR(255),
          `venue_id` INT,
          `country` VARCHAR(100),
          `founded` INT,
          `national` TINYINT(1) DEFAULT 0,
          `venue_name` VARCHAR(255),
          `venue_capacity` INT,
          `coach_id` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "team_leagues" => "CREATE TABLE IF NOT EXISTS `team_leagues` (
          `team_id` INT,
          `league_id` INT,
          `season` INT,
          PRIMARY KEY (`team_id`, `league_id`, `season`)
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
          `played` INT DEFAULT 0,
          `win` INT DEFAULT 0,
          `draw` INT DEFAULT 0,
          `lose` INT DEFAULT 0,
          `goals_for` INT DEFAULT 0,
          `goals_against` INT DEFAULT 0,
          `home_stats_json` TEXT,
          `away_stats_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `team_id`),
          INDEX (`team_id`)
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
          `status` VARCHAR(20),
          `score_home` INT,
          `score_away` INT,
          `score_home_ht` INT,
          `score_away_ht` INT,
          `venue_id` INT,
          `last_detailed_update` TIMESTAMP NULL,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_events" => "CREATE TABLE IF NOT EXISTS `fixture_events` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_lineups" => "CREATE TABLE IF NOT EXISTS `fixture_lineups` (
          `fixture_id` INT,
          `team_id` INT,
          `formation` VARCHAR(20),
          `coach_id` INT,
          `start_xi_json` LONGTEXT,
          `substitutes_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_statistics" => "CREATE TABLE IF NOT EXISTS `fixture_statistics` (
          `fixture_id` INT,
          `team_id` INT,
          `stats_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_player_stats" => "CREATE TABLE IF NOT EXISTS `fixture_player_stats` (
          `fixture_id` INT,
          `team_id` INT,
          `player_id` INT,
          `stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`, `player_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_injuries" => "CREATE TABLE IF NOT EXISTS `fixture_injuries` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `fixture_id` INT,
          `team_id` INT,
          `player_id` INT,
          `player_name` VARCHAR(100),
          `type` VARCHAR(50),
          `reason` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX (`fixture_id`)
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

      "team_stats" => "CREATE TABLE IF NOT EXISTS `team_stats` (
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
          `avg_goals_for` DECIMAL(8,2),
          `avg_goals_against` DECIMAL(8,2),
          `full_stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `league_id`, `season`)
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
          `requests_limit` INT DEFAULT 7500,
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

      "player_statistics" => "CREATE TABLE IF NOT EXISTS `player_statistics` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `player_id` INT,
          `team_id` INT,
          `league_id` INT,
          `season` INT,
          `stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `player_team_league_season` (`player_id`, `team_id`, `league_id`, `season`)
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "bookmakers" => "CREATE TABLE IF NOT EXISTS `bookmakers` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "bet_types" => "CREATE TABLE IF NOT EXISTS `bet_types` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `type` ENUM('pre-match', 'live') DEFAULT 'pre-match',
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "fixture_odds" => "CREATE TABLE IF NOT EXISTS `fixture_odds` (
          `fixture_id` INT,
          `bookmaker_id` INT,
          `bet_id` INT,
          `odds_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`)
          -- INDEX (`fixture_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "live_odds" => "CREATE TABLE IF NOT EXISTS `live_odds` (
          `fixture_id` INT PRIMARY KEY,
          `odds_json` LONGTEXT,
          `status_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "transfers" => "CREATE TABLE IF NOT EXISTS `transfers` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "trophies" => "CREATE TABLE IF NOT EXISTS `trophies` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      "sidelined" => "CREATE TABLE IF NOT EXISTS `sidelined` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `player_id` INT,
          `coach_id` INT,
          `type` VARCHAR(100),
          `start_date` DATE,
          `end_date` DATE,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX (`player_id`),
          INDEX (`coach_id`)
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
          "ALTER TABLE leagues ADD COLUMN country VARCHAR(100) AFTER type",
          "ALTER TABLE leagues ADD COLUMN logo VARCHAR(255) AFTER country",
          "ALTER TABLE leagues ADD COLUMN country_name VARCHAR(100) AFTER logo",
          "ALTER TABLE leagues ADD COLUMN coverage_json TEXT AFTER country_name",
          "ALTER TABLE leagues ADD COLUMN season INT AFTER coverage_json"
      ],
      "teams" => [
          "ALTER TABLE teams ADD COLUMN code VARCHAR(10) AFTER name",
          "ALTER TABLE teams ADD COLUMN logo VARCHAR(255) AFTER code",
          "ALTER TABLE teams ADD COLUMN venue_id INT AFTER logo",
          "ALTER TABLE teams ADD COLUMN country VARCHAR(100) AFTER venue_id",
          "ALTER TABLE teams ADD COLUMN founded INT AFTER country",
          "ALTER TABLE teams ADD COLUMN national TINYINT(1) DEFAULT 0 AFTER founded",
          "ALTER TABLE teams ADD COLUMN venue_name VARCHAR(255) AFTER national",
          "ALTER TABLE teams ADD COLUMN venue_capacity INT AFTER venue_name",
          "ALTER TABLE teams ADD COLUMN coach_id INT AFTER venue_capacity"
      ],
      "fixtures" => [
          "ALTER TABLE fixtures ADD COLUMN round VARCHAR(100) AFTER league_id",
          "ALTER TABLE fixtures ADD COLUMN status_short VARCHAR(10) AFTER date",
          "ALTER TABLE fixtures ADD COLUMN status_long VARCHAR(50) AFTER status_short",
          "ALTER TABLE fixtures ADD COLUMN elapsed INT AFTER status_long",
          "ALTER TABLE fixtures ADD COLUMN status VARCHAR(20) AFTER elapsed",
          "ALTER TABLE fixtures ADD COLUMN score_home INT AFTER status",
          "ALTER TABLE fixtures ADD COLUMN score_away INT AFTER score_home",
          "ALTER TABLE fixtures ADD COLUMN score_home_ht INT AFTER score_away",
          "ALTER TABLE fixtures ADD COLUMN score_away_ht INT AFTER score_home_ht",
          "ALTER TABLE fixtures ADD COLUMN venue_id INT AFTER score_away_ht",
          "ALTER TABLE fixtures ADD COLUMN last_detailed_update TIMESTAMP NULL"
      ],
      "standings" => [
          "ALTER TABLE standings ADD COLUMN group_name VARCHAR(100) AFTER form",
          "ALTER TABLE standings ADD COLUMN description TEXT AFTER group_name",
          "ALTER TABLE standings ADD COLUMN played INT DEFAULT 0 AFTER description",
          "ALTER TABLE standings ADD COLUMN win INT DEFAULT 0 AFTER played",
          "ALTER TABLE standings ADD COLUMN draw INT DEFAULT 0 AFTER win",
          "ALTER TABLE standings ADD COLUMN lose INT DEFAULT 0 AFTER draw",
          "ALTER TABLE standings ADD COLUMN goals_for INT DEFAULT 0 AFTER lose",
          "ALTER TABLE standings ADD COLUMN goals_against INT DEFAULT 0 AFTER goals_for",
          "ALTER TABLE standings ADD COLUMN home_stats_json TEXT AFTER goals_against",
          "ALTER TABLE standings ADD COLUMN away_stats_json TEXT AFTER home_stats_json"
      ],
      "team_stats" => [
          "ALTER TABLE team_stats ADD COLUMN full_stats_json LONGTEXT AFTER avg_goals_against"
      ],
      "coaches" => [
          "ALTER TABLE coaches ADD COLUMN birth_date DATE AFTER age",
          "ALTER TABLE coaches ADD COLUMN birth_country VARCHAR(100) AFTER birth_date",
          "ALTER TABLE coaches ADD COLUMN career_json LONGTEXT AFTER team_id"
      ],
      "predictions" => [
          "ALTER TABLE predictions ADD COLUMN comparison_json LONGTEXT AFTER advice"
      ],
      "fixture_odds" => [
          "ALTER TABLE fixture_odds ADD COLUMN odds_json LONGTEXT AFTER bet_id"
      ],
      "api_usage" => [
          "ALTER TABLE api_usage ADD COLUMN requests_limit INT DEFAULT 7500 AFTER id"
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

  echo "\n✨ Database sincronizzato con successo v8.4.\n";

} catch (\Throwable $e) {
  echo "❌ Errore critico: " . $e->getMessage() . "\n";
}

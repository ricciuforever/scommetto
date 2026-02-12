<?php
// fix_db.php - Scommetto DB Migration & Repair Script v8.8
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
  $db = Database::getInstance()->getConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  echo "🚀 Inizio riparazione e migrazione database (v8.8)...\n";

  // --- 0. PULIZIA VECCHIE TABELLE DEPRECATE ---
  $deprecated = ["team_squads"];
  foreach ($deprecated as $oldTable) {
    try {
      $db->exec("DROP TABLE IF EXISTS `$oldTable` ");
      echo "🧹 Tabella deprecata `$oldTable` rimossa.\n";
    } catch (\Exception $e) {
    }
  }

  // --- 1. CREAZIONE TABELLE BASE (Consolidate) ---

  $tables = [
    "countries" => "CREATE TABLE IF NOT EXISTS `countries` (
          `name` VARCHAR(100) PRIMARY KEY,
          `code` VARCHAR(10),
          `flag` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "seasons" => "CREATE TABLE IF NOT EXISTS `seasons` (
          `year` INT PRIMARY KEY,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "leagues" => "CREATE TABLE IF NOT EXISTS `leagues` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `type` VARCHAR(50),
          `country` VARCHAR(100),
          `logo` VARCHAR(255),
          `country_name` VARCHAR(100),
          `coverage_json` TEXT,
          `season` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "league_seasons" => "CREATE TABLE IF NOT EXISTS `league_seasons` (
          `league_id` INT,
          `year` INT,
          `is_current` TINYINT(1) DEFAULT 0,
          `start_date` DATE,
          `end_date` DATE,
          `last_teams_sync` TIMESTAMP NULL,
          `last_fixtures_sync` TIMESTAMP NULL,
          `last_standings_sync` TIMESTAMP NULL,
          PRIMARY KEY (`league_id`, `year`)
      )",

    "rounds" => "CREATE TABLE IF NOT EXISTS `rounds` (
          `league_id` INT,
          `season` INT,
          `round_name` VARCHAR(100),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `season`, `round_name`)
      )",

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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "team_leagues" => "CREATE TABLE IF NOT EXISTS `team_leagues` (
          `team_id` INT,
          `league_id` INT,
          `season` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `league_id`, `season`)
      )",

    "venues" => "CREATE TABLE IF NOT EXISTS `venues` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `address` VARCHAR(255),
          `city` VARCHAR(100),
          `country` VARCHAR(100),
          `capacity` INT,
          `surface` VARCHAR(50),
          `image` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "standings" => "CREATE TABLE IF NOT EXISTS `standings` (
          `league_id` INT,
          `team_id` INT,
          `season` INT,
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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `team_id`, `season`)
      )",

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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "fixture_events" => "CREATE TABLE IF NOT EXISTS `fixture_events` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `fixture_id` INT,
          `team_id` INT,
          `player_id` INT,
          `assist_id` INT,
          `time_elapsed` INT,
          `time_extra` INT,
          `type` VARCHAR(50),
          `detail` VARCHAR(100),
          `comments` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "fixture_lineups" => "CREATE TABLE IF NOT EXISTS `fixture_lineups` (
          `fixture_id` INT,
          `team_id` INT,
          `formation` VARCHAR(20),
          `coach_id` INT,
          `start_xi_json` LONGTEXT,
          `substitutes_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`)
      )",

    "fixture_statistics" => "CREATE TABLE IF NOT EXISTS `fixture_statistics` (
          `fixture_id` INT,
          `team_id` INT,
          `stats_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`)
      )",

    "player_career" => "CREATE TABLE IF NOT EXISTS `player_career` (
          `player_id` INT,
          `team_id` INT,
          `seasons_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`player_id`, `team_id`)
      )",

    "fixture_player_stats" => "CREATE TABLE IF NOT EXISTS `fixture_player_stats` (
          `fixture_id` INT,
          `team_id` INT,
          `player_id` INT,
          `stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `team_id`, `player_id`)
      )",

    "fixture_injuries" => "CREATE TABLE IF NOT EXISTS `fixture_injuries` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `fixture_id` INT,
          `team_id` INT,
          `player_id` INT,
          `player_name` VARCHAR(100),
          `type` VARCHAR(50),
          `reason` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "league_topstats" => "CREATE TABLE IF NOT EXISTS `league_topstats` (
          `league_id` INT,
          `season` INT,
          `type` VARCHAR(50),
          `json_data` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`league_id`, `season`, `type`)
      )",

    "player_transfers" => "CREATE TABLE IF NOT EXISTS `player_transfers` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `player_id` INT NOT NULL,
          `update_date` DATETIME,
          `transfers_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "player_trophies" => "CREATE TABLE IF NOT EXISTS `player_trophies` (
          `player_id` INT NOT NULL,
          `trophies_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`player_id`)
      )",

    "player_sidelined" => "CREATE TABLE IF NOT EXISTS `player_sidelined` (
          `player_id` INT NOT NULL,
          `sidelined_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`player_id`)
      )",

    "live_bet_types" => "CREATE TABLE IF NOT EXISTS `live_bet_types` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(255),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "h2h_records" => "CREATE TABLE IF NOT EXISTS `h2h_records` (
          `team1_id` INT,
          `team2_id` INT,
          `h2h_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`team1_id`, `team2_id`)
      )",

    "team_stats" => "CREATE TABLE IF NOT EXISTS `team_stats` (
          `team_id` INT,
          `league_id` INT,
          `season` INT,
          `date` DATE DEFAULT '0000-00-00',
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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `league_id`, `season`, `date`)
      )",

    "bets" => "CREATE TABLE IF NOT EXISTS `bets` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `fixture_id` VARCHAR(100) NOT NULL,
          `bookmaker_id` INT NULL,
          `bookmaker_name` VARCHAR(100) NULL,
          `match_name` VARCHAR(255) NOT NULL,
          `advice` TEXT,
          `market` VARCHAR(100),
          `odds` DECIMAL(8,2),
          `stake` DECIMAL(8,2),
          `urgency` VARCHAR(50),
          `confidence` INT DEFAULT 0,
          `status` VARCHAR(20) DEFAULT 'pending',
          `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `result` VARCHAR(50),
          `betfair_id` VARCHAR(100) NULL,
          `adm_id` VARCHAR(100) NULL,
          `notes` TEXT NULL
      )",

    "api_usage" => "CREATE TABLE IF NOT EXISTS `api_usage` (
          `id` INT PRIMARY KEY DEFAULT 1,
          `requests_limit` INT DEFAULT 75000,
          `requests_used` INT DEFAULT 0,
          `requests_remaining` INT DEFAULT 75000,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "analyses" => "CREATE TABLE IF NOT EXISTS `analyses` (
          `fixture_id` INT PRIMARY KEY,
          `last_checked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `prediction_raw` TEXT
      )",

    "fixture_predictions" => "CREATE TABLE IF NOT EXISTS `fixture_predictions` (
          `fixture_id` INT PRIMARY KEY,
          `prediction_json` TEXT,
          `comparison_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "player_seasons" => "CREATE TABLE IF NOT EXISTS `player_seasons` (
          `year` INT PRIMARY KEY,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "predictions" => "CREATE TABLE IF NOT EXISTS `predictions` (
          `fixture_id` INT PRIMARY KEY,
          `advice` TEXT,
          `comparison_json` TEXT,
          `percent_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "player_statistics" => "CREATE TABLE IF NOT EXISTS `player_statistics` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `player_id` INT,
          `team_id` INT,
          `league_id` INT,
          `season` INT,
          `stats_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

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
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "squads" => "CREATE TABLE IF NOT EXISTS `squads` (
          `team_id` INT,
          `player_id` INT,
          `position` VARCHAR(50),
          `number` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `player_id`)
      )",

    "team_seasons" => "CREATE TABLE IF NOT EXISTS `team_seasons` (
          `team_id` INT,
          `year` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`team_id`, `year`)
      )",

    "bookmakers" => "CREATE TABLE IF NOT EXISTS `bookmakers` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "bet_types" => "CREATE TABLE IF NOT EXISTS `bet_types` (
          `id` INT PRIMARY KEY,
          `name` VARCHAR(100),
          `type` VARCHAR(20) DEFAULT 'pre-match',
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "fixture_odds" => "CREATE TABLE IF NOT EXISTS `fixture_odds` (
          `fixture_id` INT,
          `bookmaker_id` INT,
          `bet_id` INT,
          `odds_json` LONGTEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`)
      )",

    "live_odds" => "CREATE TABLE IF NOT EXISTS `live_odds` (
          `fixture_id` INT PRIMARY KEY,
          `odds_json` LONGTEXT,
          `status_json` TEXT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "transfers" => "CREATE TABLE IF NOT EXISTS `transfers` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `player_id` INT,
          `transfer_date` DATE,
          `type` VARCHAR(100),
          `team_out_id` INT,
          `team_in_id` INT,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "trophies" => "CREATE TABLE IF NOT EXISTS `trophies` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `player_id` INT DEFAULT NULL,
          `coach_id` INT DEFAULT NULL,
          `league` VARCHAR(100),
          `country` VARCHAR(100),
          `season` VARCHAR(20),
          `place` VARCHAR(50),
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "sidelined" => "CREATE TABLE IF NOT EXISTS `sidelined` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `player_id` INT,
          `coach_id` INT,
          `type` VARCHAR(100),
          `start_date` DATE,
          `end_date` DATE,
          `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "system_state" => "CREATE TABLE IF NOT EXISTS `system_state` (
          `key` VARCHAR(100) PRIMARY KEY,
          `value` TEXT,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )",

    "gianik_bets" => "CREATE TABLE IF NOT EXISTS `gianik_bets` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `market_id` VARCHAR(100),
          `market_name` VARCHAR(255),
          `event_name` VARCHAR(255),
          `sport` VARCHAR(50),
          `selection_id` VARCHAR(100),
          `runner_name` VARCHAR(255),
          `odds` REAL,
          `stake` REAL,
          `status` VARCHAR(50) DEFAULT 'pending',
          `type` VARCHAR(20) DEFAULT 'virtual',
          `betfair_id` VARCHAR(100),
          `motivation` TEXT,
          `profit` REAL DEFAULT 0,
          `settled_at` DATETIME,
          `period` VARCHAR(20),
          `last_seen_at` " . (\App\Services\Database::getInstance()->isSQLite() ? "DATETIME" : "TIMESTAMP") . " DEFAULT CURRENT_TIMESTAMP,
          `missing_count` INTEGER DEFAULT 0,
          `created_at` " . (\App\Services\Database::getInstance()->isSQLite() ? "DATETIME" : "TIMESTAMP") . " DEFAULT CURRENT_TIMESTAMP
      )",

    "gianik_system_state" => "CREATE TABLE IF NOT EXISTS `gianik_system_state` (
          `key` VARCHAR(100) PRIMARY KEY,
          `value` TEXT,
          `updated_at` " . (\App\Services\Database::getInstance()->isSQLite() ? "DATETIME" : "TIMESTAMP") . " DEFAULT CURRENT_TIMESTAMP
      )",

    "gianik_skipped_matches" => "CREATE TABLE IF NOT EXISTS `gianik_skipped_matches` (
          `id` " . (\App\Services\Database::getInstance()->isSQLite() ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
          `event_name` VARCHAR(255),
          `market_name` VARCHAR(255),
          `reason` VARCHAR(255),
          `details` TEXT,
          `created_at` " . (\App\Services\Database::getInstance()->isSQLite() ? "DATETIME" : "TIMESTAMP") . " DEFAULT CURRENT_TIMESTAMP
      )"
  ];

  foreach ($tables as $name => $sql) {
    $db->exec($sql);
    echo "✅ Tabella '$name' verificata/creata.\n";
  }

  // Inizializza api_usage se vuota
  $count = $db->query("SELECT COUNT(*) FROM api_usage")->fetchColumn();
  if ($count == 0) {
    $db->exec("INSERT INTO api_usage (id, requests_limit, requests_used, requests_remaining) VALUES (1, 75000, 0, 75000)");
    echo "✅ Tabella 'api_usage' inizializzata.\n";
  }

  // Inizializza bookmakers base
  if (\App\Services\Database::getInstance()->isSQLite()) {
    $db->exec("INSERT OR IGNORE INTO bookmakers (id, name) VALUES (3, 'Betfair'), (7, 'William Hill')");
  } else {
    $db->exec("INSERT IGNORE INTO bookmakers (id, name) VALUES (3, 'Betfair'), (7, 'William Hill')");
  }
  echo "✅ Bookmakers base inizializzati.\n";

  // --- 2. PATCHING COLONNE MANCANTI ---

  $patches = [
    "leagues_updates" => [
      "ALTER TABLE leagues ADD COLUMN type VARCHAR(50) AFTER name",
      "ALTER TABLE leagues ADD COLUMN country VARCHAR(100) AFTER type",
      "ALTER TABLE league_seasons ADD COLUMN last_teams_sync TIMESTAMP NULL",
      "ALTER TABLE league_seasons ADD COLUMN last_fixtures_sync TIMESTAMP NULL",
      "ALTER TABLE league_seasons ADD COLUMN last_standings_sync TIMESTAMP NULL",
      "ALTER TABLE leagues ADD COLUMN logo VARCHAR(255) AFTER country",
      "ALTER TABLE leagues ADD COLUMN country_name VARCHAR(100) AFTER logo",
      "ALTER TABLE leagues ADD COLUMN coverage_json TEXT AFTER country_name",
      "ALTER TABLE leagues ADD COLUMN season INT AFTER coverage_json"
    ],
    "teams_updates" => [
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
    "fixtures_updates" => [
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
    "standings_updates" => [
      "ALTER TABLE standings ADD COLUMN season INT AFTER team_id",
      "ALTER TABLE standings DROP PRIMARY KEY, ADD PRIMARY KEY (league_id, team_id, season)",
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
    "team_stats_updates" => [
      "ALTER TABLE team_stats ADD COLUMN date DATE DEFAULT '0000-00-00' AFTER season",
      "ALTER TABLE team_stats DROP PRIMARY KEY, ADD PRIMARY KEY (team_id, league_id, season, date)",
      "ALTER TABLE team_stats ADD COLUMN full_stats_json LONGTEXT AFTER avg_goals_against"
    ],
    "venues_updates" => [
      "ALTER TABLE venues ADD COLUMN country VARCHAR(100) AFTER city"
    ],
    "coaches_updates" => [
      "ALTER TABLE coaches ADD COLUMN firstname VARCHAR(100) AFTER name",
      "ALTER TABLE coaches ADD COLUMN lastname VARCHAR(100) AFTER firstname",
      "ALTER TABLE coaches ADD COLUMN birth_date DATE AFTER age",
      "ALTER TABLE coaches ADD COLUMN birth_country VARCHAR(100) AFTER birth_date",
      "ALTER TABLE coaches ADD COLUMN nationality VARCHAR(100) AFTER birth_country",
      "ALTER TABLE coaches ADD COLUMN photo VARCHAR(255) AFTER nationality",
      "ALTER TABLE coaches ADD COLUMN career_json LONGTEXT AFTER team_id"
    ],
    "predictions_updates" => [
      "ALTER TABLE predictions ADD COLUMN comparison_json LONGTEXT AFTER advice"
    ],
    "fixture_odds_updates" => [
      "ALTER TABLE fixture_odds ADD COLUMN odds_json LONGTEXT AFTER bet_id"
    ],
    "api_usage_updates" => [
      "ALTER TABLE api_usage ADD COLUMN requests_limit INT DEFAULT 75000 AFTER id"
    ],
    "fixtures_indexes" => [
      "CREATE INDEX idx_fixtures_date ON fixtures(date)",
      "CREATE INDEX idx_fixtures_status ON fixtures(status_short)",
      "CREATE INDEX idx_fixtures_teams ON fixtures(team_home_id, team_away_id)"
    ],
    "players_updates" => [
      "ALTER TABLE players ADD COLUMN firstname VARCHAR(100) AFTER name",
      "ALTER TABLE players ADD COLUMN lastname VARCHAR(100) AFTER firstname",
      "ALTER TABLE players ADD COLUMN birth_date DATE AFTER age",
      "ALTER TABLE players ADD COLUMN birth_place VARCHAR(100) AFTER birth_date",
      "ALTER TABLE players ADD COLUMN birth_country VARCHAR(100) AFTER birth_place",
      "ALTER TABLE players ADD COLUMN height VARCHAR(20) AFTER birth_country",
      "ALTER TABLE players ADD COLUMN weight VARCHAR(20) AFTER height",
      "ALTER TABLE players ADD COLUMN injured TINYINT(1) DEFAULT 0 AFTER weight"
    ],
    "players_indexes" => [
      "CREATE INDEX idx_players_name ON players(name)"
    ],
    "bets_updates" => [
      "ALTER TABLE bets ADD COLUMN bookmaker_id INT NULL AFTER fixture_id",
      "ALTER TABLE bets ADD COLUMN bookmaker_name VARCHAR(100) NULL AFTER bookmaker_id"
    ],
    "leagues_indexes" => [
      "CREATE INDEX idx_leagues_country ON leagues(country_name)"
    ],
    "squads_indexes" => [
      "CREATE INDEX idx_squads_player ON squads(player_id)"
    ],
    "bets_identifiers" => [
      "ALTER TABLE bets ADD COLUMN betfair_id VARCHAR(100) NULL AFTER result",
      "ALTER TABLE bets ADD COLUMN adm_id VARCHAR(100) NULL AFTER betfair_id",
      "ALTER TABLE bets ADD COLUMN notes TEXT NULL AFTER adm_id"
    ],
    "bets_fix_fixture_id" => [
      "ALTER TABLE bets MODIFY COLUMN fixture_id VARCHAR(100) NOT NULL"
    ],
    "fixtures_sync_column_v2" => [
      "ALTER TABLE league_seasons ADD COLUMN last_fixtures_sync TIMESTAMP NULL"
    ],
    "global_last_updated" => [
      "ALTER TABLE countries ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE seasons ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE leagues ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE teams ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE team_leagues ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE venues ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE standings ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE fixtures ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE team_seasons ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE players ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE coaches ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
      "ALTER TABLE squads ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ],
  ];

  // --- 3. CREAZIONE VIEW ANALITICHE ---

  $views = [
    "v_match_summary" => "SELECT f.id, f.date, f.status_short,
                 t1.name as home_name, t1.logo as home_logo, f.score_home,
                 t2.name as away_name, t2.logo as away_logo, f.score_away,
                 l.name as league_name, l.country_name
          FROM fixtures f
          JOIN teams t1 ON f.team_home_id = t1.id
          JOIN teams t2 ON f.team_away_id = t2.id
          JOIN leagues l ON f.league_id = l.id"
  ];

  foreach ($views as $name => $sql) {
    $db->exec("DROP VIEW IF EXISTS `$name` ");
    $db->exec("CREATE VIEW `$name` AS $sql");
    echo "✅ View '$name' creata/aggiornata.\n";
  }

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

  echo "\n✨ Database sincronizzato con successo v8.8.\n";

} catch (\Throwable $e) {
  echo "❌ Errore critico: " . $e->getMessage() . "\n";
}

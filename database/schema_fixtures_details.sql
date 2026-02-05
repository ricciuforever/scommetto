-- Tabella Events (Gol, Cartellini, Var)
CREATE TABLE IF NOT EXISTS `fixture_events` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Lineups (Formazioni)
CREATE TABLE IF NOT EXISTS `fixture_lineups` (
  `fixture_id` INT,
  `team_id` INT,
  `formation` VARCHAR(20),
  `coach_id` INT,
  `start_xi_json` LONGTEXT,
  `substitutes_json` LONGTEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fixture_id`, `team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

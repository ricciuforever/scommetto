-- Tabella Standings (Classifiche)
CREATE TABLE IF NOT EXISTS `standings` (
  `league_id` INT,
  `team_id` INT,
  `rank` INT,
  `points` INT,
  `goals_diff` INT,
  `form` VARCHAR(50),
  `group_name` VARCHAR(100),
  `description` VARCHAR(255),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

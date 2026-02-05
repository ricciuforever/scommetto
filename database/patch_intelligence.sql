-- 9. Tabelle per i Risultati e Statistiche Fixtures
CREATE TABLE IF NOT EXISTS `fixtures` (
  `id` INT PRIMARY KEY,
  `league_id` INT,
  `team_home_id` INT,
  `team_away_id` INT,
  `date` DATETIME,
  `status` VARCHAR(20),
  `score_home` INT,
  `score_away` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`league_id`),
  INDEX (`team_home_id`),
  INDEX (`team_away_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Statistiche dettagliate Team (Season based)
CREATE TABLE IF NOT EXISTS `team_stats` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Head to Head (H2H) Cache
CREATE TABLE IF NOT EXISTS `h2h_records` (
  `team_a_id` INT,
  `team_b_id` INT,
  `match_date` DATETIME,
  `match_id` INT,
  `score_a` INT,
  `score_b` INT,
  `league_name` VARCHAR(100),
  PRIMARY KEY (`match_id`),
  INDEX (`team_a_id`, `team_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

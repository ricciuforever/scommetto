-- Tabella Leagues aggiornata con Coverage
CREATE TABLE IF NOT EXISTS `leagues` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `type` VARCHAR(50),
  `logo` VARCHAR(255),
  `country_name` VARCHAR(100),
  `coverage_json` TEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`country_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella di collegamento League - Season
CREATE TABLE IF NOT EXISTS `league_seasons` (
  `league_id` INT,
  `year` INT,
  `is_current` BOOLEAN DEFAULT FALSE,
  `start_date` DATE,
  `end_date` DATE,
  PRIMARY KEY (`league_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

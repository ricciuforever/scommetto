-- Tabella Leagues
CREATE TABLE IF NOT EXISTS `leagues` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `type` VARCHAR(50),
  `logo` VARCHAR(255),
  `country_name` VARCHAR(100),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`country_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella di collegamento League - Season
CREATE TABLE IF NOT EXISTS `league_seasons` (
  `league_id` INT,
  `year` INT,
  `is_current` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`league_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

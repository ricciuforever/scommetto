-- Tabella Venues (Stadi)
CREATE TABLE IF NOT EXISTS `venues` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(255),
  `address` VARCHAR(255),
  `city` VARCHAR(100),
  `capacity` INT,
  `surface` VARCHAR(50),
  `image` VARCHAR(255),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Teams
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `code` VARCHAR(10),
  `country` VARCHAR(100),
  `founded` INT,
  `national` BOOLEAN DEFAULT FALSE,
  `logo` VARCHAR(255),
  `venue_id` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella di collegamento Team -> League (per stagione)
CREATE TABLE IF NOT EXISTS `team_leagues` (
  `team_id` INT,
  `league_id` INT,
  `season` INT,
  PRIMARY KEY (`team_id`, `league_id`, `season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

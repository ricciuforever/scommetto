-- 1. Tabelle per i Campionati
CREATE TABLE IF NOT EXISTS `leagues` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(255),
  `country` VARCHAR(100),
  `logo` VARCHAR(255),
  `season` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabelle per le Squadre
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(255),
  `logo` VARCHAR(255),
  `country` VARCHAR(100),
  `founded` INT,
  `venue_name` VARCHAR(255),
  `venue_capacity` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Classifiche (Standings)
CREATE TABLE IF NOT EXISTS `standings` (
  `league_id` INT,
  `team_id` INT,
  `rank` INT,
  `points` INT,
  `goals_diff` INT,
  `form` VARCHAR(20),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`league_id`, `team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Allenatori (Coaches)
CREATE TABLE IF NOT EXISTS `coaches` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(255),
  `firstname` VARCHAR(255),
  `lastname` VARCHAR(255),
  `age` INT,
  `nationality` VARCHAR(100),
  `photo` VARCHAR(255),
  `team_id` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

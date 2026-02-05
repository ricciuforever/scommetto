-- Tabella Players (Profili)
CREATE TABLE IF NOT EXISTS `players` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Player Statistics (Stagionali)
CREATE TABLE IF NOT EXISTS `player_statistics` (
  `player_id` INT,
  `team_id` INT,
  `league_id` INT,
  `season` INT,
  `stats_json` LONGTEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`player_id`, `team_id`, `league_id`, `season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Infortuni
CREATE TABLE IF NOT EXISTS `fixture_injuries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fixture_id` INT,
  `team_id` INT,
  `player_id` INT,
  `player_name` VARCHAR(100),
  `type` VARCHAR(50),
  `reason` VARCHAR(255),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`fixture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Allenatori
CREATE TABLE IF NOT EXISTS `coaches` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `firstname` VARCHAR(100),
  `lastname` VARCHAR(100),
  `age` INT,
  `birth_date` DATE,
  `birth_country` VARCHAR(100),
  `nationality` VARCHAR(100),
  `height` VARCHAR(20),
  `weight` VARCHAR(20),
  `photo` VARCHAR(255),
  `team_id` INT,
  `career_json` LONGTEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

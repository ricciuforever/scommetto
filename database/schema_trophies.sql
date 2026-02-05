-- Tabella Trofei
CREATE TABLE IF NOT EXISTS `trophies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT DEFAULT NULL,
  `coach_id` INT DEFAULT NULL,
  `league` VARCHAR(100),
  `country` VARCHAR(100),
  `season` VARCHAR(20),
  `place` VARCHAR(50),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`player_id`),
  INDEX (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

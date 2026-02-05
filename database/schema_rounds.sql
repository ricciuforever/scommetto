-- Tabella Rounds (Giornate)
CREATE TABLE IF NOT EXISTS `rounds` (
  `league_id` INT,
  `season` INT,
  `round_name` VARCHAR(100),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`league_id`, `season`, `round_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

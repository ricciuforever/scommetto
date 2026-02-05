-- Tabella Trasferimenti
CREATE TABLE IF NOT EXISTS `transfers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT,
  `transfer_date` DATE,
  `type` VARCHAR(100),
  `team_out_id` INT,
  `team_in_id` INT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`player_id`),
  INDEX (`team_in_id`),
  INDEX (`team_out_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Bookmakers
CREATE TABLE IF NOT EXISTS `bookmakers` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Bets (Tipi di Scommessa)
CREATE TABLE IF NOT EXISTS `bet_types` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(100),
  `type` ENUM('pre-match', 'live') DEFAULT 'pre-match',
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella Odds (Quote Pre-match)
CREATE TABLE IF NOT EXISTS `fixture_odds` (
  `fixture_id` INT,
  `bookmaker_id` INT,
  `bet_id` INT,
  `odds_json` LONGTEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

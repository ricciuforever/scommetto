CREATE TABLE IF NOT EXISTS `bookmakers` (
    `id` INT PRIMARY KEY,
    `name` VARCHAR(255),
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bet_types` (
    `id` INT PRIMARY KEY,
    `name` VARCHAR(255),
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fixture_odds` (
    `fixture_id` INT,
    `bookmaker_id` INT,
    `bet_id` INT,
    `odds_json` JSON,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`fixture_id`, `bookmaker_id`, `bet_id`),
    INDEX (`fixture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

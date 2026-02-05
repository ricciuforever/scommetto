-- 7. Tabella per i suggerimenti e le predizioni (Cache delle API)
CREATE TABLE IF NOT EXISTS `predictions` (
  `fixture_id` INT PRIMARY KEY,
  `advice` TEXT,
  `comparison_json` TEXT,
  `percent_json` TEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

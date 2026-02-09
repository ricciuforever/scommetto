<?php
// install_league_stats.php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS league_topstats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        league_id INT NOT NULL,
        season INT NOT NULL,
        type ENUM('scorers', 'assists', 'yellowcards', 'redcards') NOT NULL,
        json_data JSON,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_stat (league_id, season, type)
    )";

    $db->exec($sql);
    echo "Tabella league_topstats creata con successo.\n";

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

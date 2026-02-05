<?php
// fix_db.php
require_once __DIR__ . '/bootstrap.php';
use App\Services\Database;

try {
    $db = Database::getInstance()->getConnection();

    echo "Controllo tabelle mancanti...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `predictions` (
      `fixture_id` INT PRIMARY KEY,
      `advice` TEXT,
      `comparison_json` TEXT,
      `percent_json` TEXT,
      `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    echo "✅ Tabella 'predictions' verificata/creata con successo.\n";

    // Altri controlli opzionali
    $db->exec("CREATE TABLE IF NOT EXISTS `analyses` (
      `fixture_id` int(11) NOT NULL PRIMARY KEY,
      `last_checked` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      `prediction_raw` text DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabella 'analyses' verificata/creata con successo.\n";

    echo "\nEsecuzione completata. Ora puoi eliminare questo file.\n";

} catch (\Exception $e) {
    echo "❌ Errore durante l'aggiornamento del database: " . $e->getMessage() . "\n";
}

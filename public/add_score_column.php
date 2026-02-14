<?php
require __DIR__ . '/bootstrap.php';

use App\Dio\DioDatabase;
use PDO;

$db = DioDatabase::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE bets ADD COLUMN score TEXT DEFAULT NULL");
    echo "✅ Colonna 'score' aggiunta con successo alla tabella 'bets'.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "⚠️ La colonna 'score' esiste già.\n";
    } else {
        echo "❌ Errore: " . $e->getMessage() . "\n";
    }
}

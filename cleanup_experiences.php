<?php
/**
 * cleanup_experiences.php
 * Rimuove le lezioni dell'AI che fanno riferimento al CSV per pulire il database.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Dio\DioDatabase;

$db = DioDatabase::getInstance()->getConnection();

try {
    $stmt = $db->prepare("DELETE FROM experiences WHERE lesson LIKE '%csv%' OR lesson LIKE '%CSV%'");
    $stmt->execute();
    $count = $stmt->rowCount();

    echo "Pulizia completata: rimosse $count lezioni che facevano riferimento al CSV.\n";
} catch (\Exception $e) {
    echo "Errore durante la pulizia: " . $e->getMessage() . "\n";
}

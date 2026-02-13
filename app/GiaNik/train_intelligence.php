<?php
// app/GiaNik/train_intelligence.php

require_once __DIR__ . '/../../bootstrap.php';

use App\GiaNik\GiaNikDatabase;
use App\Services\IntelligenceService;

echo "--- GIANIK INTELLIGENCE TRAINER ---\n";

$db = GiaNikDatabase::getInstance()->getConnection();
$intelligence = new IntelligenceService();

// 1. Pulisci vecchie metriche per ricalcolo completo
$db->exec("DELETE FROM performance_metrics");
echo "Memoria resettata. Inizio analisi storico...\n";

// 2. Recupera scommesse settled (vinte o perse)
$stmt = $db->query("SELECT * FROM bets WHERE status IN ('won', 'lost', 'settled') OR profit != 0");
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Trovate " . count($bets) . " scommesse da analizzare.\n";

foreach ($bets as $bet) {
    // Usiamo direttamente il metodo dell'IntelligenceService per consistenza
    $intelligence->learnFromBet($bet);
}

echo "Apprendimento completato con successo.\n";

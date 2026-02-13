<?php
// app/GiaNik/import_from_csv.php

require_once __DIR__ . '/../../bootstrap.php';

use App\GiaNik\GiaNikDatabase;

$csvFile = __DIR__ . '/../../ExchangeBets_Settled.csv';

if (!file_exists($csvFile)) {
    die("❌ File CSV non trovato in: $csvFile\n");
}

echo "📂 Inizio importazione sicura dal CSV...\n";

$db = GiaNikDatabase::getInstance()->getConnection();

// Svuota lo storico reale attuale per evitare duplicati o residui sporchi
echo "🧹 Svuoto lo storico reale attuale...\n";
$db->exec("DELETE FROM bets WHERE type = 'real'");

$handle = fopen($csvFile, "r");

// Salta l'intestazione
fgetcsv($handle);

$imported = 0;
$skipped = 0;

while (($data = fgetcsv($handle)) !== false) {
    // Piazzata,Chiuse,Dettagli,Tipo,Quota,Puntata,Responsabilità,Profitto,Stato
    $piazzata = $data[0];
    $chiusa = $data[1];
    $dettagli = $data[2];
    $quota = (float)str_replace(',', '.', $data[4]);
    $puntata = (float)str_replace(['"', ',', ' '], ['', '.', ''], $data[5]);
    $profitto = (float)str_replace(['"', ',', ' ', ' '], ['', '.', '', ''], $data[7]);
    $stato_raw = strtolower($data[8]);

    $status = ($stato_raw === 'vinta') ? 'won' : 'lost';

    // Parsing Dettagli: "Event Runner-Market | ID Betfair 1:12345 | ..."
    $parts = explode(' | ', $dettagli);
    $mainInfo = $parts[0] ?? '';
    $betfairIdPart = $parts[1] ?? '';

    // Estrai Betfair ID
    $betfairId = null;
    if (preg_match('/ID scommessa Betfair 1:(\d+)/', $betfairIdPart, $matches)) {
        $betfairId = $matches[1];
    }

    if (!$betfairId) {
        $skipped++;
        continue;
    }

    // Verifica duplicati
    $stmtCheck = $db->prepare("SELECT id FROM bets WHERE betfair_id = ?");
    $stmtCheck->execute([$betfairId]);
    if ($stmtCheck->fetch()) {
        $skipped++;
        continue;
    }

    // Parsing dell'evento, runner e mercato dal mainInfo
    // Formato probabile: "Team A - Team B RunnerName-MarketName"
    $eventName = "Unknown Event";
    $runnerName = "Unknown Runner";
    $marketName = "Unknown Market";

    if (preg_match('/^(.*?)\s+([^-]+)-(.*?)$/', $mainInfo, $infoMatches)) {
        $eventName = trim($infoMatches[1]);
        $runnerName = trim($infoMatches[2]);
        $marketName = trim($infoMatches[3]);
    } else {
        $eventName = $mainInfo;
    }

    // Formattazione date
    $dateCreated = date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $piazzata)));
    $dateSettled = date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $chiusa)));

    $stmtInsert = $db->prepare("INSERT INTO bets
        (betfair_id, event_name, runner_name, market_name, odds, stake, profit, status, type, sport, league, created_at, settled_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'real', 'Soccer', ?, ?, ?)");

    $stmtInsert->execute([
        $betfairId,
        $eventName,
        $runnerName,
        $marketName,
        $quota,
        $puntata,
        $profitto,
        $status,
        'Imported CSV',
        $dateCreated,
        $dateSettled
    ]);

    $imported++;
}

fclose($handle);

echo "✅ Importazione completata: $imported record importati, $skipped saltati.\n";

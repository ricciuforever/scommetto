<?php
/**
 * import_csv_dio.php
 * Script di utilità per importare scommesse dal CSV Betfair nel database di Dio.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Dio\DioDatabase;

if (PHP_SAPI !== 'cli' && !isset($_GET['run'])) {
    die("Eseguire via CLI o aggiungere ?run=1 all'URL.");
}

$csvFile = __DIR__ . '/ExchangeBets_Settled.csv';
if (!file_exists($csvFile)) {
    die("File CSV non trovato: $csvFile\n");
}

$db = DioDatabase::getInstance()->getConnection();
$handle = fopen($csvFile, 'r');
$headers = fgetcsv($handle); // Salta header

$imported = 0;
$skipped = 0;

echo "Inizio importazione CSV...\n";

while (($row = fgetcsv($handle)) !== false) {
    // 0: Piazzata, 1: Chiuse, 2: Dettagli, 3: Tipo, 4: Quota, 5: Puntata, 6: Responsabilità, 7: Profitto, 8: Stato
    if (count($row) < 9) continue;

    $placedAt = date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $row[0])));
    $settledAt = date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $row[1])));

    // Extract Betfair ID from Dettagli
    $betfairId = null;
    if (preg_match('/ID scommessa Betfair ([\d:]+)/', $row[2], $matches)) {
        $betfairId = $matches[1];
    }

    if (!$betfairId) {
        $skipped++;
        continue;
    }

    // Check if already exists
    $stmtCheck = $db->prepare("SELECT id FROM bets WHERE betfair_id = ?");
    $stmtCheck->execute([$betfairId]);
    if ($stmtCheck->fetchColumn()) {
        $skipped++;
        continue;
    }

    // Parse values
    $odds = (float)str_replace(',', '.', $row[4]);
    $stake = (float)trim(str_replace(',', '.', $row[5]), ' "');
    $profit = (float)trim(str_replace(',', '.', $row[7]), ' "');
    $statusStr = strtolower($row[8]);
    $status = 'lost';
    if (stripos($statusStr, 'vinta') !== false) $status = 'won';
    elseif (stripos($statusStr, 'rimborsata') !== false || stripos($statusStr, 'annullata') !== false) $status = 'void';

    // Basic details extraction
    $details = $row[2];
    $eventName = "Sconosciuto";
    $marketName = "Mercato";

    // Simple heuristic for event/market
    if (strpos($details, ' - ') !== false) {
        $subParts = explode('|', $details);
        $namePart = trim($subParts[0]);
        $eventName = $namePart;
    }

    $stmtInsert = $db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, status, type, betfair_id, profit, settled_at, created_at, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?, ?, ?, ?, 'Importato da CSV')");
    $stmtInsert->execute([
        'CSV_IMPORT',
        'Mercato CSV',
        $eventName,
        'Unknown',
        '0',
        'Runner CSV',
        $odds,
        $stake,
        $status,
        $betfairId,
        $profit,
        $settledAt,
        $placedAt
    ]);

    $imported++;
}

fclose($handle);

echo "Importazione completata: $imported importati, $skipped saltati.\n";

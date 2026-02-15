<?php
// import_csv_history.php
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'bootstrap.php';

use App\Dio\DioDatabase;

echo "<pre>";
echo "--- IMPORTING HISTORY FROM CSV ---\n";

$csvFile = BASE_PATH . 'ExchangeBets_Settled.csv';

if (!file_exists($csvFile)) {
    die("CRITICAL: File 'ExchangeBets_Settled.csv' not found in root.\n");
}

$db = DioDatabase::getInstance()->getConnection();

// 1. Parse CSV
$rows = array_map('str_getcsv', file($csvFile));
$header = array_shift($rows);

// Expected Header: Piazzata,Chiuse,Dettagli,Tipo,Quota,Puntata (€),Responsabilità (€),Profitto/Perdita,Stato
echo "Header found: " . implode(", ", $header) . "\n";

$imported = [];
$totalPnL = 0;

foreach ($rows as $row) {
    if (count($row) < 9)
        continue;

    // Helper to parse Italian float (1,50 -> 1.50)
    $parseFloat = function ($val) {
        $val = str_replace('.', '', $val); // remove thousands separator if any
        $val = str_replace(',', '.', $val); // comma to dot
        $val = preg_replace('/[^0-9\.\-]/', '', $val); // clean non-numeric
        return (float) $val;
    };

    // Helper for date (08-feb-26 11:21:23)
    // Italian months mapping
    $parseDate = function ($val) {
        $months = [
            'gen' => 'Jan',
            'feb' => 'Feb',
            'mar' => 'Mar',
            'apr' => 'Apr',
            'mag' => 'May',
            'giu' => 'Jun',
            'lug' => 'Jul',
            'ago' => 'Aug',
            'set' => 'Sep',
            'ott' => 'Oct',
            'nov' => 'Nov',
            'dic' => 'Dec'
        ];
        foreach ($months as $it => $en) {
            $val = str_ireplace($it, $en, $val);
        }
        // Format: d-M-y H:i:s -> 08-Feb-26 ...
        $d = DateTime::createFromFormat('d-M-y H:i:s', $val);
        return $d ? $d->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'); // Fallback
    };

    $placedAt = $parseDate($row[0]);
    $settledAt = $parseDate($row[1]);
    $details = $row[2];
    $type = $row[3]; // Punta / Banca (Back/Lay)
    $odds = $parseFloat($row[4]);
    $stake = $parseFloat($row[5]);
    $pnl = $parseFloat($row[7]);
    $statusStr = trim($row[8]); // Vinta / Persa

    // Extract Market/Runner from Details
    // Format: L Samsonova - Frech L Samsonova-Esito Finale | ID scommessa Betfair 1:417770730132 | ...
    $parts = explode('|', $details);
    $descPart = trim($parts[0] ?? '');

    // Attempt to split Event and Market
    // "L Samsonova - Frech L Samsonova-Esito Finale"
    // Usually "Event Name Runner Name-Market Name"
    // We can just store raw metadata or try to be smart.
    // For P&L, raw is fine context.
    $eventName = "Imported Event";
    $marketName = "Imported Market";
    $runnerName = "Imported Runner";
    $betfairId = preg_match('/ID scommessa Betfair (1:[0-9]+)/', $details, $m) ? $m[1] : uniqid();

    // Status normalization
    $status = 'void';
    if ($pnl > 0)
        $status = 'won';
    if ($pnl < 0)
        $status = 'lost';

    // Add to list
    $imported[] = [
        'market_id' => 'CSV_IMPORT',
        'market_name' => $marketName,
        'event_name' => $eventName . ' (' . $descPart . ')',
        'sport' => 'Imported',
        'selection_id' => '0',
        'runner_name' => $runnerName,
        'odds' => $odds,
        'stake' => $stake,
        'status' => $status,
        'betfair_id' => $betfairId,
        'profit' => $pnl,
        'settled_at' => $settledAt,
        'created_at' => $placedAt
    ];

    $totalPnL += $pnl;
}

echo "Parsed " . count($imported) . " rows. Total CSV P&L: " . number_format($totalPnL, 2) . "\n";

if (!empty($imported)) {
    // 2. Backup & Truncate
    echo "Backing up 'bets' to 'bets_backup'...\n";
    $db->exec("DROP TABLE IF EXISTS bets_backup");
    $db->exec("CREATE TABLE bets_backup AS SELECT * FROM bets");

    echo "Truncating 'bets' table...\n";
    $db->exec("DELETE FROM bets");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='bets'");

    // 3. Insert
    echo "Inserting data...\n";
    $stmt = $db->prepare("INSERT INTO bets (
        market_id, market_name, event_name, sport, selection_id, runner_name, 
        odds, stake, status, type, betfair_id, motivation, profit, settled_at, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?, 'CSV Import', ?, ?, ?
    )");

    // Sort chronological
    usort($imported, function ($a, $b) {
        return strtotime($a['settled_at']) - strtotime($b['settled_at']);
    });

    foreach ($imported as $bet) {
        $stmt->execute([
            $bet['market_id'],
            $bet['market_name'],
            $bet['event_name'],
            $bet['sport'],
            $bet['selection_id'],
            $bet['runner_name'],
            $bet['odds'],
            $bet['stake'],
            $bet['status'],
            $bet['betfair_id'],
            $bet['profit'],
            $bet['settled_at'],
            $bet['created_at']
        ]);
    }

    // 4. Update Balance
    $initial = 100.00;
    $newBalance = $initial + $totalPnL;
    $db->prepare("UPDATE system_state SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'virtual_balance'")
        ->execute([number_format($newBalance, 2, '.', '')]);

    // Reset mode
    $db->exec("UPDATE system_state SET value = 'real', updated_at = CURRENT_TIMESTAMP WHERE key = 'operational_mode'");

    echo "Imported successfully. New Balance: $newBalance\n";
}

echo "--- CSV IMPORT COMPLETED ---\n";
echo "</pre>";
?>
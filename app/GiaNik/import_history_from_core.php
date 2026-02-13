<?php
// app/GiaNik/import_history_from_core.php

require_once __DIR__ . '/../../bootstrap.php';

use App\GiaNik\GiaNikDatabase;
use App\Config\Config;

// 1. Connessione al DB DESTINAZIONE (SQLite)
$sqlite = GiaNikDatabase::getInstance()->getConnection();

// 2. Connessione al DB SORGENTE (MySQL)
try {
    $mysql = Config::getDB();
    echo "✅ Connesso al Database Centrale (MySQL)\n";
} catch (\Exception $e) {
    echo "❌ Errore connessione MySQL: " . $e->getMessage() . "\n";
    echo "⚠️ Assicurati che le credenziali nel file .env siano corrette (DB_HOST, DB_NAME, DB_USER, DB_PASS).\n";
    exit(1);
}

echo "⏳ Inizio analisi storico scommesse dal Core...\n";

// 3. Recupero Scommesse Storiche con JOIN per League ID
// Nota: Nella tabella 'bets' core il profitto non è memorizzato, va calcolato da stake e odds.
// Inoltre la colonna mercato si chiama 'market', non 'market_name'.
$sql = "
    SELECT
        b.status,
        b.stake,
        b.odds,
        b.market,
        f.league_id
    FROM bets b
    LEFT JOIN fixtures f ON b.fixture_id = f.id
    WHERE b.status IN ('won', 'lost')
";

try {
    $stmt = $mysql->query($sql);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Prova senza league_id se fallisce
    echo "⚠️ Query con JOIN fallita (" . $e->getMessage() . "), provo recupero base...\n";
    try {
        $stmt = $mysql->query("SELECT status, stake, odds, market FROM bets WHERE status IN ('won', 'lost')");
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        die("❌ Errore query MySQL: " . $ex->getMessage() . "\n");
    }
}

echo "📊 Trovate " . count($bets) . " scommesse storiche nel Core.\n";

$metrics = [];

// 4. Aggregazione Dati in Memoria
foreach ($bets as $bet) {
    $status = strtolower($bet['status']);
    $stake = (float)$bet['stake'];
    $odds = (float)$bet['odds'];
    $leagueId = $bet['league_id'] ?? null;

    // Calcolo Profitto (nella core DB non c'è la colonna profit)
    if ($status === 'won') {
        $profit = $stake * ($odds - 1);
    } else {
        $profit = -$stake;
    }

    $keys = [];

    // A. Metrica per Mercato (usiamo 'market' della core DB)
    $marketName = $bet['market'] ?? 'UNKNOWN';
    $mName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $marketName));
    $keys[] = "MARKET_{$mName}";

    // B. Metrica per Lega
    if ($leagueId) {
        $keys[] = "LEAGUE_{$leagueId}";
    }

    foreach ($keys as $k) {
        if (!isset($metrics[$k])) {
            $metrics[$k] = ['bets' => 0, 'wins' => 0, 'stake' => 0, 'profit' => 0];
        }
        $metrics[$k]['bets']++;
        $metrics[$k]['stake'] += $stake;
        $metrics[$k]['profit'] += $profit;
        if ($status === 'won') {
            $metrics[$k]['wins']++;
        }
    }
}

// 5. Inserimento nel Cervello di GiaNik (SQLite)
echo "💾 Scrittura metriche su GiaNik...\n";

$stmtInsert = $sqlite->prepare("INSERT OR REPLACE INTO performance_metrics
    (context_type, context_id, total_bets, wins, losses, total_stake, total_profit, roi, last_updated)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");

foreach ($metrics as $key => $data) {
    list($type, $id) = explode('_', $key, 2);
    $losses = $data['bets'] - $data['wins'];
    $roi = ($data['stake'] > 0) ? ($data['profit'] / $data['stake']) * 100 : 0;

    $stmtInsert->execute([
        $type,
        $id,
        $data['bets'],
        $data['wins'],
        $losses,
        $data['stake'],
        $data['profit'],
        round($roi, 2)
    ]);
}

echo "✅ MIGRAZIONE CORE COMPLETATA!\n";

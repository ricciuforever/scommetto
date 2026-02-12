<?php
// app/GiaNik/import_history_from_core.php

require_once __DIR__ . '/../../bootstrap.php';

use App\GiaNik\GiaNikDatabase;
use App\Config\Config;
use PDO;

// 1. Connessione al DB DESTINAZIONE (SQLite)
$sqlite = GiaNikDatabase::getInstance()->getConnection();

// 2. Connessione al DB SORGENTE (MySQL)
try {
    $mysql = Config::getDB();
    echo "✅ Connesso al Database Centrale (MySQL)\n";
} catch (PDOException $e) {
    die("❌ Errore connessione MySQL: " . $e->getMessage() . "\n");
}

echo "⏳ Inizio analisi storico scommesse dal Core...\n";

// 3. Recupero Scommesse Storiche con JOIN per League ID
$sql = "
    SELECT
        b.status,
        b.profit,
        b.stake,
        b.market_name,
        f.league_id
    FROM bets b
    LEFT JOIN fixtures f ON b.fixture_id = f.id
    WHERE b.status IN ('won', 'lost')
";

try {
    $stmt = $mysql->query($sql);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Prova senza league_id se fallisce (magari fixture_id non c'è in bets)
    echo "⚠️ Query con JOIN fallita, provo recupero base...\n";
    try {
        $stmt = $mysql->query("SELECT status, profit, stake, market_name FROM bets WHERE status IN ('won', 'lost')");
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
    $profit = (float)$bet['profit'];
    $stake = (float)$bet['stake'];
    $leagueId = $bet['league_id'] ?? null;

    if ($status === 'lost' && $profit >= 0) {
        $profit = -$stake;
    }

    $keys = [];

    // A. Metrica per Mercato
    $mName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $bet['market_name'] ?? 'UNKNOWN'));
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
    (metric_key, total_bets, wins, losses, total_stake, net_profit, roi, last_updated)
    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");

foreach ($metrics as $key => $data) {
    $losses = $data['bets'] - $data['wins'];
    $roi = ($data['stake'] > 0) ? ($data['profit'] / $data['stake']) * 100 : 0;

    $stmtInsert->execute([
        $key,
        $data['bets'],
        $data['wins'],
        $losses,
        $data['stake'],
        $data['profit'],
        round($roi, 2)
    ]);
}

echo "✅ MIGRAZIONE CORE COMPLETATA!\n";

<?php
// app/GiaNik/learn_from_history.php
require_once __DIR__ . '/../../bootstrap.php';
use App\GiaNik\GiaNikDatabase;
use App\Services\IntelligenceService;

$db = GiaNikDatabase::getInstance()->getConnection();
$intelligence = new IntelligenceService();

echo "🧠 Inizio apprendimento dallo storico locale (SQLite)...\n";

// 1. Recupera tutte le scommesse SETTLED (vinte o perse)
$stmt = $db->query("SELECT * FROM bets WHERE status IN ('won', 'lost')");
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$metrics = [];

foreach ($bets as $bet) {
    $status = strtolower($bet['status']);
    $profit = (float)$bet['profit'];
    $stake = (float)$bet['stake'];
    $commission = (float)($bet['commission'] ?? 0);

    // Calcolo profitto netto reale
    $netProfit = $profit - $commission;
    if ($status === 'lost' && $netProfit >= 0) {
        $netProfit = -$stake;
    }

    // Identifichiamo le chiavi metriche
    $keys = [];

    // A. Performance per Mercato
    $mName = $intelligence->normalizeMarketName($bet['market_name'] ?? 'UNKNOWN');
    $keys[] = "MARKET_{$mName}";

    // B. Performance per Lega
    if (!empty($bet['league_id'])) {
        $keys[] = "LEAGUE_{$bet['league_id']}";
    }

    // C. Sport
    $sport = strtoupper($bet['sport'] ?? 'SOCCER');
    $keys[] = "SPORT_{$sport}";

    // D. Bucket
    $odds = (float)($bet['odds'] ?? 0);
    $bucket = $intelligence->getOddsBucket($odds);
    $keys[] = "BUCKET_{$bucket}";

    foreach ($keys as $k) {
        if (!isset($metrics[$k])) {
            $metrics[$k] = ['bets' => 0, 'wins' => 0, 'stake' => 0, 'profit' => 0];
        }
        $metrics[$k]['bets']++;
        $metrics[$k]['stake'] += $stake;
        $metrics[$k]['profit'] += $netProfit;
        if ($status === 'won') $metrics[$k]['wins']++;
    }
}

// 2. Salva nel cervello (performance_metrics)
$stmtInsert = $db->prepare("INSERT OR REPLACE INTO performance_metrics
    (context_type, context_id, total_bets, wins, losses, total_stake, profit_loss, roi, last_updated)
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
    echo "  -> Aggiornato $type|$id: Bets: {$data['bets']} | ROI: " . round($roi, 2) . "%\n";
}

echo "✅ Apprendimento locale completato.\n";

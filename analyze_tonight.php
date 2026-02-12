<?php
$dbFile = 'c:\Users\ricci\OneDrive\Desktop\SVILUPPO\scommetto\data\gianik.sqlite';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filter bets from today (2026-02-11) since 18:00:00
    $stmt = $pdo->query("SELECT created_at, event_name, market_name, runner_name, odds, stake, status, motivation 
                         FROM bets 
                         WHERE created_at >= '2026-02-11 18:00:00' 
                         ORDER BY created_at DESC");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'total' => count($bets),
        'won' => 0,
        'lost' => 0,
        'pending' => 0,
        'total_profit' => 0,
        'details' => $bets
    ];

    foreach ($bets as $bet) {
        if ($bet['status'] === 'won') {
            $summary['won']++;
            $summary['total_profit'] += ($bet['odds'] - 1) * $bet['stake'];
        } elseif ($bet['status'] === 'lost') {
            $summary['lost']++;
            $summary['total_profit'] -= $bet['stake'];
        } else {
            $summary['pending']++;
        }
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

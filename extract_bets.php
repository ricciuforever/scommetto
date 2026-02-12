<?php
$dbFile = 'c:\Users\ricci\OneDrive\Desktop\SVILUPPO\scommetto\data\gianik.sqlite';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT created_at, event_name, runner_name, odds, stake, status, motivation FROM bets ORDER BY created_at DESC LIMIT 50");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($bets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

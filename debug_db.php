<?php
require_once __DIR__ . '/app/Config/Config.php';
require_once __DIR__ . '/app/GiaNik/GiaNikDatabase.php';

use App\GiaNik\GiaNikDatabase;

try {
    $db = GiaNikDatabase::getInstance()->getConnection();
    echo "--- STATS BETS REAL ---\n";
    $stats = $db->query("SELECT status, COUNT(*) as count, SUM(profit) as total_profit, SUM(stake) as total_stake FROM bets WHERE type = 'real' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    print_r($stats);

    echo "\n--- ULTIME 10 SCOMMESSE REALI CHIUSE ---\n";
    $recent = $db->query("SELECT event_name, runner_name, odds, stake, profit, status, settled_at FROM bets WHERE type = 'real' AND status IN ('won', 'lost') ORDER BY COALESCE(settled_at, created_at) DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    print_r($recent);

} catch (\Throwable $e) {
    echo "Errore: " . $e->getMessage();
}

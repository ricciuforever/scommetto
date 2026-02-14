<?php
require __DIR__ . '/bootstrap.php';

use App\Dio\DioDatabase;
use PDO;

$db = DioDatabase::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 20");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Recent Logs:\n";
foreach ($logs as $log) {
    echo "[{$log['created_at']}] {$log['event_name']} ({$log['market_name']}) - {$log['action']}\n";
    echo "Motivazione: {$log['motivation']}\n\n";
}

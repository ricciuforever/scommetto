<?php
// app/bootstrap.php is in the root, so from public/ we go up one level
require_once __DIR__ . '/../bootstrap.php';

use App\Dio\DioDatabase;
use PDO;

// Protect this file with a simple check or IP restriction if needed, 
// but for debugging now we keep it open or maybe require a query param
// e.g. ?key=debug
if (($_GET['key'] ?? '') !== 'debug') {
    die('Access Denied');
}

header('Content-Type: text/plain');

try {
    $db = DioDatabase::getInstance()->getConnection();
    // Fetch last 50 logs to see plenty of history
    $stmt = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 50");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "--- LOG DI SISTEMA DIO (Mondo Reale) ---\n\n";
    foreach ($logs as $log) {
        echo "[{$log['created_at']}] {$log['event_name']} ({$log['market_name']})\n";
        echo "Action: " . strtoupper($log['action']) . "\n";
        echo "Confidence: {$log['confidence']}%\n";
        echo "Motivazione: {$log['motivation']}\n";
        echo "---------------------------------------------------\n";
    }
} catch (Exception $e) {
    echo "Errore DB: " . $e->getMessage();
}

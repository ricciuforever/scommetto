<?php
require_once __DIR__ . '/bootstrap.php';
$db = \App\Services\Database::getInstance()->getConnection();
$history = $db->query("SELECT COUNT(*) FROM bets")->fetchColumn();
echo "Total bets in DB: " . $history . "\n";
$history_won = $db->query("SELECT COUNT(*) FROM bets WHERE status = 'won'")->fetchColumn();
echo "Won bets in DB: " . $history_won . "\n";

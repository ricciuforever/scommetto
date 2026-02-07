<?php
require_once __DIR__ . '/bootstrap.php';
$db = \App\Services\Database::getInstance()->getConnection();
$res = $db->query("SELECT status, COUNT(*) as count FROM bets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);

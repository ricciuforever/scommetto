<?php
require_once __DIR__ . '/bootstrap.php';
$db = \App\GiaNik\GiaNikDatabase::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE bets");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($columns);

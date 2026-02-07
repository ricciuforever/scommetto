<?php
require_once __DIR__ . '/bootstrap.php';
$db = \App\Services\Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE bets");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);

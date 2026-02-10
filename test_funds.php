<?php
require_once __DIR__ . '/bootstrap.php';
$bf = new \App\Services\BetfairService();
$res = $bf->getFunds();
header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);

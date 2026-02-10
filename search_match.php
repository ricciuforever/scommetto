<?php
require 'vendor/autoload.php';
$db = \App\Services\Database::getInstance()->getConnection();
$name = '%Zrinjski%';
$stmt = $db->prepare("SELECT * FROM fixtures WHERE team_home_name LIKE ? OR team_away_name LIKE ? LIMIT 5");
$stmt->execute([$name, $name]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

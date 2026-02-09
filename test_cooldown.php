<?php
require_once __DIR__ . '/bootstrap.php';
use App\Services\BetfairService;
use App\Config\Config;

// Mock configuration to allow authenticate() to run
putenv("BETFAIR_APP_KEY=mock_key");
putenv("BETFAIR_USERNAME=mock_user");
putenv("BETFAIR_PASSWORD=mock_pass");
Config::init();

$bf = new BetfairService();

echo "Tentativo 1:\n";
$bf->request('listEventTypes', []);

echo "\nTentativo 2 (immediato, dovrebbe essere in cooldown):\n";
$bf->request('listEventTypes', []);

echo "\nContenuto log:\n";
echo file_get_contents(__DIR__ . '/logs/betfair_debug.log');

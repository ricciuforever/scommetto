<?php
require_once 'bootstrap.php';
use App\Config\Config;
use App\Services\BetfairService;
use App\Controllers\SyncController;

echo "--- CHECK CONFIGURAZIONE ---\n";
echo "Simulation Mode: " . (Config::isSimulationMode() ? 'ON' : 'OFF') . "\n";
echo "Initial Bankroll: " . Config::getInitialBankroll() . "\n";
echo "Virtual Bookie ID: " . Config::getVirtualBookmakerId() . "\n";

$bf = new BetfairService();
echo "Betfair Configured: " . ($bf->isConfigured() ? 'YES' : 'NO') . "\n";

if (!$bf->isConfigured()) {
    echo "ATTENZIONE: Betfair non è configurato. Il sync non funzionerà.\n";
    exit;
}

echo "\n--- AVVIO SYNC LIVE (TEST) ---\n";
$sync = new SyncController();
$sync->syncLive();
echo "\n--- SYNC COMPLETATO ---\n";

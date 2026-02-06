<?php
// populate.php - Manual script to populate the database with main leagues data
require_once __DIR__ . '/bootstrap.php';

use App\Controllers\SyncController;
use App\Config\Config;

$sync = new SyncController();
$season = 2024;
set_time_limit(0);

echo "🚀 Inizio popolamento database...\n";

foreach (Config::PREMIUM_LEAGUES as $leagueId) {
    echo "📦 Sincronizzazione Lega ID: $leagueId...\n";
    try {
        $sync->deepSync($leagueId, $season);
        echo "✅ Completato.\n";
    } catch (\Throwable $e) {
        echo "❌ Errore: " . $e->getMessage() . "\n";
    }
    // Sleep to avoid hitting rate limits too fast if needed
    sleep(1);
}

echo "\n✨ Operazione completata. Il database è ora popolato con i dati base.\n";
echo "Il cron job continuerà a mantenere i dati aggiornati ogni minuto.\n";

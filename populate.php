<?php
// populate.php - Manual script to populate the database with main leagues data
require_once __DIR__ . '/bootstrap.php';

use App\Controllers\SyncController;
use App\Config\Config;

$sync = new SyncController();
$season = 2024;
set_time_limit(0);

echo "🚀 Inizio popolamento database (v4.0)...\n";
echo "Nota: Lo script eseguirà una sincronizzazione profonda per ogni lega premium.\n\n";

foreach (Config::PREMIUM_LEAGUES as $leagueId) {
    echo "📦 Sincronizzazione Lega ID: $leagueId...\n";
    try {
        // Obfuscate output to stay clean but show keys
        ob_start();
        $sync->deepSync($leagueId, $season);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            echo "   ✅ Panoramica: " . json_encode($data['overview']) . "\n";
            echo "   ✅ Top Stats: " . json_encode($data['top_stats']) . "\n";
            echo "   ✅ Fixtures: " . json_encode($data['fixtures']) . "\n";
            echo "   ✅ Dettagli Team: " . json_encode($data['details']) . "\n";
            echo "   ✅ Dettagli Match: " . json_encode($data['match_details']) . "\n";
            echo "   ✅ Quote: " . json_encode($data['odds']) . "\n";
            echo "   ✅ Infortuni: " . json_encode($data['injuries']) . "\n";

            if (!empty($data['details']['errors'])) {
                echo "   ⚠️ Avvisi Team: " . count($data['details']['errors']) . " errori ignorati.\n";
            }
        } else {
            echo "   ❌ Risposta fallita: " . ($data['error'] ?? substr($output, 0, 100)) . "\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Errore Critico: " . $e->getMessage() . "\n";
    }
    // Sleep to avoid hitting rate limits too fast
    echo "   ⏳ Attesa per rate limit...\n";
    sleep(5);
}

echo "\n✨ Operazione completata. Il database è ora popolato con i dati base.\n";
echo "Il cron job continuerà a mantenere i dati aggiornati a rotazione ogni minuto.\n";

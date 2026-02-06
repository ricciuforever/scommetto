<?php
// populate.php - Script manuale per popolare il database
require_once __DIR__ . '/bootstrap.php';

use App\Controllers\SyncController;
use App\Config\Config;

$sync = new SyncController();

/**
 * LOGICA DINAMICA PER LA STAGIONE
 * Se siamo tra Gennaio (01) e Giugno (06), la stagione API è l'anno precedente.
 * Esempio: Febbraio 2026 -> Stagione 2025
 */
$season = (int)date('m') <= 6 ? (int)date('Y') - 1 : (int)date('Y');

set_time_limit(0);

echo "🚀 Inizio popolamento database per la stagione: $season (v4.1)...\n";
echo "Nota: Lo script eseguirà una sincronizzazione profonda per ogni lega premium.\n\n";

foreach (Config::PREMIUM_LEAGUES as $leagueId) {
    echo "📦 Sincronizzazione Lega ID: $leagueId...\n";
    try {
        // Obfuscate output to stay clean but show keys
        ob_start();
        
        // IMPORTANTE: Ora passiamo la variabile $season dinamica
        $sync->deepSync($leagueId, $season);
        
        $output = ob_get_clean();

        $data = json_decode($output, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            echo "    ✅ Panoramica: " . json_encode($data['overview']) . "\n";
            echo "    ✅ Top Stats: " . json_encode($data['top_stats']) . "\n";
            echo "    ✅ Fixtures: " . json_encode($data['fixtures']) . "\n";
            echo "    ✅ Dettagli Team: " . json_encode($data['details']) . "\n";
            echo "    ✅ Dettagli Match: " . json_encode($data['match_details']) . "\n";
            echo "    ✅ Quote: " . json_encode($data['odds']) . "\n";
            echo "    ✅ Infortuni: " . json_encode($data['injuries']) . "\n";

            if (!empty($data['details']['errors'])) {
                echo "    ⚠️ Avvisi Team: " . count($data['details']['errors']) . " errori ignorati.\n";
            }
        } else {
            echo "    ❌ Risposta fallita: " . ($data['error'] ?? substr($output, 0, 100)) . "\n";
        }
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) ob_get_clean();
        echo "    ❌ Errore Critico: " . $e->getMessage() . "\n";
    }
    
    // Sleep to avoid hitting rate limits
    echo "    ⏳ Attesa 5s per rate limit...\n";
    sleep(5);
}

echo "\n✨ Operazione completata per la stagione $season.\n";

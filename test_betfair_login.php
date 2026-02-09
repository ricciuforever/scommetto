<?php
require_once 'bootstrap.php';
use App\Services\BetfairService;
use App\Config\Config;

echo "\n--- TEST CONNESSIONE BETFAIR ---\n";
echo "Username: " . Config::get('BETFAIR_USERNAME') . "\n";
// Non stampiamo la password in chiaro per sicurezza
echo "Password Configured: " . (!empty(Config::get('BETFAIR_PASSWORD')) ? "YES" : "NO") . "\n";
echo "AppKey: " . Config::get('BETFAIR_APP_KEY') . "\n";

$bf = new BetfairService();
$token = $bf->authenticate(true); // Force re-login

if ($token) {
    echo "LOGIN RIUSCITO! Token: " . substr($token, 0, 10) . "...\n";

    // Test Sport Fetching
    echo "\nRecupero Sport...\n";
    $sports = $bf->getEventTypes();

    if (!empty($sports['result'])) {
        echo "Trovati " . count($sports['result']) . " sport.\n";
        foreach ($sports['result'] as $s) {
            echo "- " . $s['eventType']['name'] . " (ID: " . $s['eventType']['id'] . ")\n";
        }
    } else {
        echo "ERRORE: Nessuno sport trovato. Risposta API:\n";
        print_r($sports);
    }

} else {
    echo "LOGIN FALLITO.\n";
    echo "Controlla logs/betfair_debug.log per i dettagli.\n";
}

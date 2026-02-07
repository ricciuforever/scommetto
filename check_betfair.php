<?php
// check_betfair.php
// Script di diagnostica per verificare la connessione con Betfair

require_once __DIR__ . '/bootstrap.php';

use App\Services\BetfairService;
use App\Config\Config;

echo "--- DIAGNOSTICA BETFAIR ---\n";

$bf = new BetfairService();

if (!$bf->isConfigured()) {
    echo "[ERRORE] Betfair non è configurato correttamente nel file .env\n";
    echo "Controlla: BETFAIR_APP_KEY_DELAY, BETFAIR_USERNAME, BETFAIR_PASSWORD, BETFAIR_CERT_PATH\n";
    exit(1);
}

echo "[INFO] Configurazione rilevata. Tentativo di autenticazione...\n";

// Il metodo authenticate() è privato, useremo un metodo pubblico che lo attiva
$result = $bf->request('listEventTypes', ['filter' => new stdClass()]);

if (isset($result['result'])) {
    echo "[SUCCESSO] Autenticazione completata!\n";
    echo "[INFO] Trovati " . count($result['result']) . " tipi di evento.\n";

    // Prova a cercare match di calcio oggi
    echo "[INFO] Cerco match di calcio attivi...\n";
    $events = $bf->request('listEvents', [
        'filter' => [
            'eventTypeIds' => ["1"],
            'marketStartTime' => [
                'from' => date('Y-m-d\TH:i:s\Z'),
                'to' => date('Y-m-d\TH:i:s\Z', strtotime('+24 hours'))
            ]
        ]
    ]);

    if (isset($events['result'])) {
        echo "[SUCCESSO] Trovati " . count($events['result']) . " eventi nelle prossime 24 ore.\n";
        foreach (array_slice($events['result'], 0, 5) as $e) {
            echo " - " . $e['event']['name'] . " (ID: " . $e['event']['id'] . ")\n";
        }
    } else {
        echo "[AVVISO] Nessun evento trovato o errore nella query eventi.\n";
        print_r($events);
    }

} else {
    echo "[ERRORE] Autenticazione fallita o API non raggiungibile.\n";
    echo "Dettagli errore:\n";
    print_r($result);
    echo "\nAssicurati che i percorsi dei certificati siano corretti e che siano leggibili dal PHP.\n";
}

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

    if (isset($events['result']) && !empty($events['result'])) {
        echo "[SUCCESSO] Trovati " . count($events['result']) . " eventi nelle prossime 24 ore.\n";
        $firstEvent = $events['result'][0]['event'];
        echo "[INFO] Test findMarket per: " . $firstEvent['name'] . "\n";

        $market = $bf->findMarket($firstEvent['name'], 'MATCH_ODDS');
        if ($market) {
            echo "[SUCCESSO] Mercato trovato! ID: " . $market['marketId'] . "\n";
            echo "[INFO] Runners trovati: " . count($market['runners']) . "\n";
            foreach($market['runners'] as $r) {
                echo "   - " . $r['runnerName'] . " (ID: " . $r['selectionId'] . ")\n";
            }

            // Test mapping
            $testAdvice = "Winner: " . $market['runners'][0]['runnerName'];
            $sid = $bf->mapAdviceToSelection($testAdvice, $market['runners']);
            if ($sid) {
                echo "[SUCCESSO] Mapping riuscito per '$testAdvice' -> Selection ID: $sid\n";
            } else {
                echo "[ERRORE] Mapping fallito per '$testAdvice'\n";
            }
        } else {
            echo "[ERRORE] Mercato MATCH_ODDS non trovato per questo evento.\n";
        }

        foreach (array_slice($events['result'], 1, 4) as $e) {
            echo " - " . $e['event']['name'] . " (ID: " . $e['event']['id'] . ")\n";
        }
    } elseif (isset($events['result'])) {
        echo "[AVVISO] Nessun evento trovato nelle prossime 24 ore.\n";
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

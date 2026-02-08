<?php
// test_bet.php
// Script per simulare un piazzamento scommessa (lettura ID mercato e selezione)

require_once __DIR__ . '/bootstrap.php';

use App\Services\BetfairService;

echo "=== TEST BETFAIR: SIMULAZIONE PIAZZAMENTO ===\n";

$bf = new BetfairService();

if (!$bf->isConfigured() && empty($_ENV['BETFAIR_SESSION_TOKEN'])) {
    die("[ERRORE] Configurazione mancante.\n");
}

// 1. Cerca eventi live
echo "[1] Ricerca eventi live...\n";
$events = $bf->request('listEvents', [
    'filter' => [
        'eventTypeIds' => ["1"],
        'marketStartTime' => [
            'from' => date('Y-m-d\TH:i:s\Z'),
            'to' => date('Y-m-d\TH:i:s\Z', strtotime('+24 hours'))
        ]
    ]
]);

if (empty($events['result'])) {
    die("[ERRORE] Nessun evento trovato.\n");
}

// Prendi il primo evento
$match = $events['result'][0]['event'];
echo "[INFO] Evento selezionato: " . $match['name'] . " (ID: " . $match['id'] . ")\n";

// 2. Cerca il mercato MATCH_ODDS (Esito Finale 1X2)
echo "[2] Ricerca mercato MATCH_ODDS...\n";
$market = $bf->findMarket($match['name'], 'MATCH_ODDS');

if (!$market) {
    die("[ERRORE] Mercato MATCH_ODDS non trovato per questo evento.\n");
}

echo "[SUCCESSO] Mercato trovato! ID: " . $market['marketId'] . "\n";
echo "Runners disponibili:\n";
foreach ($market['runners'] as $runner) {
    echo " - " . $runner['runnerName'] . " (Selection ID: " . $runner['selectionId'] . ")\n";
}

// 3. Simulazione parametri scommessa
echo "\n[3] Simulazione parametri scommessa:\n";
$selectionId = $market['runners'][0]['selectionId']; // Punta sulla prima squadra (Casa)
$price = 2.0; // Quota fittizia
$size = 5.0;  // Stake fittizio

echo "Dati pronti per API 'placeOrders':\n";
echo " -> Market ID: " . $market['marketId'] . "\n";
echo " -> Selection ID: " . $selectionId . " (" . $market['runners'][0]['runnerName'] . ")\n";
echo " -> Prezzo Limite: " . number_format($price, 2) . "\n";
echo " -> Importo: " . number_format($size, 2) . "\n";

echo "\n[CONCLUSIONE] Test completato. Se avessi chiamato placeBet(), l'ordine sarebbe partito con questi dati.\n";

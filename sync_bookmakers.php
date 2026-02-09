<?php
// sync_bookmakers.php - Script per sincronizzare i bookmaker dal API-Football

require_once __DIR__ . '/bootstrap.php';

use App\Services\FootballApiService;
use App\Models\Bookmaker;

echo "🔄 Sincronizzazione Bookmakers...\n\n";

try {
    $api = new FootballApiService();
    $bookmakerModel = new Bookmaker();

    echo "📡 Chiamata API per ottenere i bookmaker...\n";
    $result = $api->fetchBookmakers();

    if (empty($result['response'])) {
        echo "⚠️  Nessun bookmaker ricevuto dall'API\n";
        exit(1);
    }

    $count = count($result['response']);
    echo "✅ Ricevuti {$count} bookmakers\n\n";

    $saved = 0;
    foreach ($result['response'] as $bookmaker) {
        if ($bookmakerModel->save($bookmaker)) {
            $saved++;
            echo "  ✓ {$bookmaker['name']} (ID: {$bookmaker['id']})\n";
        } else {
            echo "  ✗ Errore salvando {$bookmaker['name']}\n";
        }
    }

    echo "\n✅ Sincronizzazione completata: {$saved}/{$count} bookmakers salvati\n";

} catch (\Throwable $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
    exit(1);
}

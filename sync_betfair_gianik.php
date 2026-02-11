<?php
// sync_betfair_gianik.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Config/Config.php';
require_once __DIR__ . '/app/GiaNik/GiaNikDatabase.php';

use App\GiaNik\GiaNikDatabase;

header('Content-Type: text/html; charset=utf-8');

$db = GiaNikDatabase::getInstance()->getConnection();

$betsToSync = [
    [
        'betfair_id' => '1:418084591980',
        'event_name' => 'Pumas - San Diego FC',
        'market_name' => 'Esito Finale',
        'runner_name' => 'Pumas',
        'odds' => 1.82,
        'stake' => 5.00,
        'created_at' => '2026-02-11 02:09:23'
    ],
    [
        'betfair_id' => '1:418084585946',
        'event_name' => 'Pumas - San Diego FC',
        'market_name' => 'Over/Under 2.5 gol',
        'runner_name' => 'Over 2.5 goal',
        'odds' => 1.71,
        'stake' => 6.00,
        'created_at' => '2026-02-11 02:09:16'
    ],
    [
        'betfair_id' => '1:418084137926',
        'event_name' => 'EC Vitoria Salvador - Flamengo',
        'market_name' => 'Over/Under 2.5 gol',
        'runner_name' => 'Over 2.5 goal',
        'odds' => 1.93,
        'stake' => 5.50,
        'created_at' => '2026-02-11 02:01:15'
    ],
    [
        'betfair_id' => '1:418083659784',
        'event_name' => 'Deportivo Tachira - The Strongest',
        'market_name' => 'Over/Under 2.5 gol',
        'runner_name' => 'Under 2.5 goal',
        'odds' => 1.61,
        'stake' => 6.00,
        'created_at' => '2026-02-11 01:51:09'
    ],
    [
        'betfair_id' => '1:418082915358',
        'event_name' => 'EC Vitoria Salvador - Flamengo',
        'market_name' => 'Esito Finale',
        'runner_name' => 'Flamengo',
        'odds' => 1.74,
        'stake' => 7.50,
        'created_at' => '2026-02-11 01:35:21'
    ],
    [
        'betfair_id' => '1:418082909842',
        'event_name' => 'Llaneros - Deportivo Pasto',
        'market_name' => 'Over/Under 1.5 gol',
        'runner_name' => 'Under 1.5 goal',
        'odds' => 2.80,
        'stake' => 3.00,
        'created_at' => '2026-02-11 01:35:14'
    ],
    [
        'betfair_id' => '1:418082855475',
        'event_name' => 'EC Vitoria Salvador - Flamengo',
        'market_name' => 'Esito Finale',
        'runner_name' => 'Flamengo',
        'odds' => 1.71,
        'stake' => 7.50,
        'created_at' => '2026-02-11 01:34:09'
    ],
    [
        'betfair_id' => '1:418082810155',
        'event_name' => 'Llaneros - Deportivo Pasto',
        'market_name' => 'Over/Under 1.5 gol',
        'runner_name' => 'Over 1.5 goal',
        'odds' => 1.51,
        'stake' => 5.00,
        'created_at' => '2026-02-11 01:33:13'
    ],
    [
        'betfair_id' => '1:418082718784',
        'event_name' => 'EC Vitoria Salvador - Flamengo',
        'market_name' => 'Esito Finale',
        'runner_name' => 'Flamengo',
        'odds' => 1.70,
        'stake' => 7.50,
        'created_at' => '2026-02-11 01:31:15'
    ],
    [
        'betfair_id' => '1:418082636926',
        'event_name' => 'Llaneros - Deportivo Pasto',
        'market_name' => 'Over/Under 1.5 gol',
        'runner_name' => 'Under 1.5 goal',
        'odds' => 3.10,
        'stake' => 3.00,
        'created_at' => '2026-02-11 01:29:18'
    ]
];

echo "<h2>Sincronizzazione Scommesse GiaNik (SQLite)</h2>";
echo "<ul>";

foreach ($betsToSync as $bet) {
    // Verifica se esiste già
    $stmt = $db->prepare("SELECT COUNT(*) FROM bets WHERE betfair_id = ?");
    $stmt->execute([$bet['betfair_id']]);
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        echo "<li><span style='color:orange'>[SALTATA]</span> ID {$bet['betfair_id']} già presente: {$bet['event_name']} ({$bet['runner_name']})</li>";
    } else {
        // Inserimento
        $stmtInsert = $db->prepare("INSERT INTO bets 
            (betfair_id, event_name, market_name, runner_name, odds, stake, status, type, sport, motivation, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'real', 'Soccer', 'Sincronizzazione manuale da Betfair Hist', ?)");

        $success = $stmtInsert->execute([
            $bet['betfair_id'],
            $bet['event_name'],
            $bet['market_name'],
            $bet['runner_name'],
            $bet['odds'],
            $bet['stake'],
            $bet['created_at']
        ]);

        if ($success) {
            echo "<li><span style='color:green'>[INSERITA]</span> ID {$bet['betfair_id']} aggiunta con successo: {$bet['event_name']} ({$bet['runner_name']})</li>";
        } else {
            echo "<li><span style='color:red'>[ERRORE]</span> Errore durante l'inserimento di ID {$bet['betfair_id']}</li>";
        }
    }
}

echo "</ul>";
echo "<p>Sincronizzazione completata.</p>";

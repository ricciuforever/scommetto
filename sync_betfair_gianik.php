<?php
// sync_betfair_gianik.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/app/Config/Config.php';
require_once __DIR__ . '/app/GiaNik/GiaNikDatabase.php';

use App\GiaNik\GiaNikDatabase;

header('Content-Type: text/html; charset=utf-8');

try {
    $db = GiaNikDatabase::getInstance()->getConnection();
} catch (\Throwable $e) {
    die("Errore connessione database: " . $e->getMessage());
}

// Lista consolidata delle scommesse (Prime e Seconde segnalazioni)
$betsToSync = [
    // --- LISTA 1 (Con ID Betfair) ---
    ['id' => '1:418084591980', 'event' => 'Pumas - San Diego FC', 'market' => 'Esito Finale', 'runner' => 'Pumas', 'odds' => 1.82, 'stake' => 5.00],
    ['id' => '1:418084585946', 'event' => 'Pumas - San Diego FC', 'market' => 'Over/Under 2.5 gol', 'runner' => 'Over 2.5 goal', 'odds' => 1.71, 'stake' => 6.00],
    ['id' => '1:418084137926', 'event' => 'EC Vitoria Salvador - Flamengo', 'market' => 'Over/Under 2.5 gol', 'runner' => 'Over 2.5 goal', 'odds' => 1.93, 'stake' => 5.50],
    ['id' => '1:418083659784', 'event' => 'Deportivo Tachira - The Strongest', 'market' => 'Over/Under 2.5 gol', 'runner' => 'Under 2.5 goal', 'odds' => 1.61, 'stake' => 6.00],
    ['id' => '1:418082915358', 'event' => 'EC Vitoria Salvador - Flamengo', 'market' => 'Esito Finale', 'runner' => 'Flamengo', 'odds' => 1.74, 'stake' => 7.50],
    ['id' => '1:418082909842', 'event' => 'Llaneros - Deportivo Pasto', 'market' => 'Over/Under 1.5 gol', 'runner' => 'Under 1.5 goal', 'odds' => 2.80, 'stake' => 3.00],
    ['id' => '1:418082855475', 'event' => 'EC Vitoria Salvador - Flamengo', 'market' => 'Esito Finale', 'runner' => 'Flamengo', 'odds' => 1.71, 'stake' => 7.50],
    ['id' => '1:418082810155', 'event' => 'Llaneros - Deportivo Pasto', 'market' => 'Over/Under 1.5 gol', 'runner' => 'Over 1.5 goal', 'odds' => 1.51, 'stake' => 5.00],
    ['id' => '1:418082718784', 'event' => 'EC Vitoria Salvador - Flamengo', 'market' => 'Esito Finale', 'runner' => 'Flamengo', 'odds' => 1.70, 'stake' => 7.50],
    ['id' => '1:418082636926', 'event' => 'Llaneros - Deportivo Pasto', 'market' => 'Over/Under 1.5 gol', 'runner' => 'Under 1.5 goal', 'odds' => 3.10, 'stake' => 3.00],

    // --- LISTA 2 (Nuove o Varianti) ---
    ['id' => null, 'event' => 'Pumas UNAM v San Diego FC', 'market' => 'Over/Under 2.5 Goals', 'runner' => 'Over 2.5 Goals', 'odds' => 1.64, 'stake' => 6.00],
    ['id' => null, 'event' => 'Flamengo v Nova Iguacu', 'market' => 'Match Odds', 'runner' => 'Flamengo', 'odds' => 1.70, 'stake' => 7.50],
    ['id' => null, 'event' => 'EC Vitoria Salvador v Flamengo', 'market' => 'Over/Under 2.5 Goals', 'runner' => 'Over 2.5 Goals', 'odds' => 1.84, 'stake' => 5.50],
    ['id' => null, 'event' => 'Llaneros FC v Deportivo Pasto', 'market' => 'Over/Under 2.5 Goals', 'runner' => 'Under 2.5 Goals', 'odds' => 1.25, 'stake' => 4.50],
];

echo "<h2>Sincronizzazione Avanzata GiaNik (SQLite)</h2>";
echo "<ul>";

foreach ($betsToSync as $bet) {
    // 1. Controllo per ID Betfair (se disponibile)
    $exists = false;
    if ($bet['id']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM bets WHERE betfair_id = ?");
        $stmt->execute([$bet['id']]);
        if ($stmt->fetchColumn() > 0)
            $exists = true;
    }

    // 2. Se non c'è match per ID, proviamo match elastico (Quota + Stake + parte del nome evento)
    if (!$exists) {
        $searchName = '%' . explode(' ', trim($bet['event']))[0] . '%'; // Prende la prima parola del team casa
        $stmt = $db->prepare("SELECT COUNT(*) FROM bets WHERE odds = ? AND stake = ? AND event_name LIKE ?");
        $stmt->execute([$bet['odds'], $bet['stake'], $searchName]);
        if ($stmt->fetchColumn() > 0)
            $exists = true;
    }

    if ($exists) {
        echo "<li><span style='color:orange'>[SALTATA]</span> Già presente: {$bet['event']} ({$bet['runner']}) @{$bet['odds']}</li>";
    } else {
        // Inserimento
        $stmtInsert = $db->prepare("INSERT INTO bets 
            (betfair_id, event_name, market_name, runner_name, odds, stake, status, type, sport, motivation, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'real', 'Soccer', 'Sincronizzazione manuale flessibile', CURRENT_TIMESTAMP)");

        $success = $stmtInsert->execute([
            $bet['id'],
            $bet['event'],
            $bet['market'],
            $bet['runner'],
            $bet['odds'],
            $bet['stake']
        ]);

        if ($success) {
            echo "<li><span style='color:green'>[INSERITA]</span> Aggiunta: {$bet['event']} ({$bet['runner']})</li>";
        } else {
            echo "<li><span style='color:red'>[ERRORE]</span> Errore per {$bet['event']}</li>";
        }
    }
}

echo "</ul><p>Fine sincronizzazione.</p>";

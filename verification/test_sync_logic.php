<?php
// verification/test_sync_logic.php

require_once __DIR__ . '/../bootstrap.php';

use App\GiaNik\GiaNikDatabase;

try {
    $db = GiaNikDatabase::getInstance()->getConnection();

    // 1. Setup: Clean and insert a poisoned record
    $db->exec("DELETE FROM bets WHERE betfair_id = 'test-bet-123'");

    $db->prepare("INSERT INTO bets (betfair_id, event_name, market_name, runner_name, odds, stake, status, type, sport)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([
           'test-bet-123',
           'WRONG EVENT NAME (AI hallucination)',
           'Wrong Market',
           'Wrong Runner',
           2.08, // Old AI odds
           4.50,
           'pending',
           'real',
           'Soccer'
       ]);

    echo "Inserted poisoned record.\n";

    // 2. Mock Betfair data (Simulating what syncWithBetfair would do)
    $o = [
        'betId' => 'test-bet-123',
        'status' => 'pending',
        'profit' => 0,
        'commission' => 0,
        'marketId' => 'test-market-999',
        'odds' => 2.17, // REAL matched odds
        'stake' => 4.50,
        'sizeMatched' => 4.50,
        'placedDate' => date('Y-m-d H:i:s')
    ];

    $finalEventName = 'Alianza Atletico v Alianza Lima'; // Correct name from Betfair
    $finalMarketName = 'Over/Under 0.5 Goals';
    $finalRunnerName = 'Over 0.5 Goals';
    $finalLeagueName = 'Primera Division';
    $fixtureId = 12345;
    $placedDate = gmdate('Y-m-d H:i:s');
    $betId = $o['betId'];

    // 3. Run the UPDATE logic (Extracted from GiaNikController::syncWithBetfair)
    $stmt = $db->prepare("SELECT id FROM bets WHERE betfair_id = ? OR betfair_id = ?");
    $stmt->execute([$betId, '1:' . $betId]);
    $dbId = $stmt->fetchColumn();

    if ($dbId) {
        echo "Found record in DB with ID: $dbId. Updating...\n";

        $stmtUpdate = $db->prepare("UPDATE bets SET status = :status, profit = :profit, commission = :commission, market_id = :market_id, market_name = :market_name, event_name = :event_name, runner_name = :runner_name, odds = :odds, stake = :stake, league = :league, league_id = COALESCE(league_id, :league_id), fixture_id = COALESCE(fixture_id, :fixture_id), created_at = :created_at, betfair_id = :betfair_id, size_matched = :size_matched WHERE id = :id");
        $stmtUpdate->execute([
            ':status' => $o['status'],
            ':profit' => $o['profit'],
            ':commission' => $o['commission'],
            ':market_id' => $o['marketId'],
            ':market_name' => $finalMarketName,
            ':event_name' => $finalEventName,
            ':runner_name' => $finalRunnerName,
            ':odds' => $o['odds'],
            ':stake' => $o['stake'],
            ':league' => $finalLeagueName,
            ':league_id' => null,
            ':fixture_id' => $fixtureId,
            ':created_at' => $placedDate,
            ':betfair_id' => $betId,
            ':size_matched' => $o['sizeMatched'] ?? 0,
            ':id' => $dbId
        ]);
        echo "Update executed.\n";
    } else {
        echo "Record NOT found in DB!\n";
    }

    // 4. Verification
    $stmt = $db->prepare("SELECT * FROM bets WHERE betfair_id = 'test-bet-123'");
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\nUpdated Record:\n";
    echo "Event Name: " . $updated['event_name'] . " (Expected: Alianza Atletico v Alianza Lima)\n";
    echo "Odds: " . $updated['odds'] . " (Expected: 2.17)\n";
    echo "Market Name: " . $updated['market_name'] . " (Expected: Over/Under 0.5 Goals)\n";

    if ($updated['event_name'] === $finalEventName && $updated['odds'] == 2.17) {
        echo "\nSUCCESS: Sync logic correctly updated the record!\n";
    } else {
        echo "\nFAILURE: Sync logic did not update correctly.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

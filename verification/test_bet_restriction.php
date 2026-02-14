<?php
// verification/test_bet_restriction.php

require 'bootstrap.php';

use App\GiaNik\Controllers\GiaNikController;
use App\GiaNik\GiaNikDatabase;

$controller = new GiaNikController();
$db = GiaNikDatabase::getInstance()->getConnection();

// Use Reflection to access private method
$reflection = new ReflectionClass(GiaNikController::class);
$method = $reflection->getMethod('isBetAlreadyPendingForMatch');
$method->setAccessible(true);

// 1. Setup: Ensure we have a pending bet
$db->exec("DELETE FROM bets WHERE event_name = 'Test Match v Another'");
$db->exec("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, status, type, fixture_id)
           VALUES ('1.123', 'Match Odds', 'Test Match v Another', 'Soccer', '123', 'Test Match', 1.5, 2.0, 'pending', 'virtual', 999999)");

echo "--- Test 1: Identical Name ---\n";
$isPending = $method->invoke($controller, 999999, 'Test Match v Another');
echo "Is pending (fixture 999999, 'Test Match v Another'): " . ($isPending ? "YES" : "NO") . "\n";

echo "\n--- Test 2: Different Name, Same Fixture ID ---\n";
$isPending = $method->invoke($controller, 999999, 'Test Match vs Another');
echo "Is pending (fixture 999999, 'Test Match vs Another'): " . ($isPending ? "YES" : "NO") . "\n";

echo "\n--- Test 3: Different Name (varied), NO Fixture ID ---\n";
$isPending = $method->invoke($controller, null, 'Test Match vs Another');
echo "Is pending (NO fixture, 'Test Match vs Another'): " . ($isPending ? "YES" : "NO") . "\n";

echo "\n--- Test 4: Completely Different Match ---\n";
$isPending = $method->invoke($controller, 111111, 'Different Team v Someone');
echo "Is pending (fixture 111111, 'Different Team v Someone'): " . ($isPending ? "YES" : "NO") . "\n";

// Cleanup
$db->exec("DELETE FROM bets WHERE event_name = 'Test Match v Another'");

echo "\nVerification complete.\n";

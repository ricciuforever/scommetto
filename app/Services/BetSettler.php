<?php
// app/Services/BetSettler.php

namespace App\Services;

use App\Models\Bet;
use App\Services\Database;

class BetSettler
{
    private $betModel;

    public function __construct()
    {
        $this->betModel = new Bet();
    }

    /**
     * Settles bets using live matches data (Zero API Cost)
     */
    public function settleFromLive($liveMatches)
    {
        $pending = array_filter($this->betModel->getAll(), function ($b) {
            return $b['status'] === 'pending';
        });

        if (empty($pending)) return 0;

        $settledCount = 0;
        foreach ($pending as $bet) {
            foreach ($liveMatches as $m) {
                if ($m['fixture']['id'] == $bet['fixture_id']) {
                    if ($this->processSettlement($bet, $m)) {
                        $settledCount++;
                    }
                    break;
                }
            }
        }
        return $settledCount;
    }

    /**
     * Settles bets using database state (Zero API Cost)
     */
    public function settleFromDatabase()
    {
        $pending = array_filter($this->betModel->getAll(), function ($b) {
            return $b['status'] === 'pending';
        });

        if (empty($pending)) return 0;

        $db = Database::getInstance()->getConnection();
        $settledCount = 0;

        foreach ($pending as $bet) {
            $stmt = $db->prepare("SELECT * FROM fixtures WHERE id = ?");
            $stmt->execute([$bet['fixture_id']]);
            $fixture = $stmt->fetch();

            if ($fixture) {
                // Mapping DB fields to expected match data structure
                $matchData = [
                    'fixture' => ['status' => ['short' => $fixture['status_short']]],
                    'goals' => ['home' => (int)$fixture['score_home'], 'away' => (int)$fixture['score_away']],
                    'score' => [
                        'halftime' => [
                            'home' => ($fixture['score_home_ht'] !== null) ? (int)$fixture['score_home_ht'] : null,
                            'away' => ($fixture['score_away_ht'] !== null) ? (int)$fixture['score_away_ht'] : null
                        ]
                    ]
                ];

                if ($this->processSettlement($bet, $matchData)) {
                    $settledCount++;
                }
            }
        }
        return $settledCount;
    }

    /**
     * Returns true if settled, false if still pending
     */
    public function processSettlement($bet, $matchData)
    {
        $statusShort = $matchData['fixture']['status']['short'];
        $homeGoals = $matchData['goals']['home'];
        $awayGoals = $matchData['goals']['away'];
        $market = strtolower($bet['market']);

        $isFinished = in_array($statusShort, ['FT', 'AET', 'PEN']);
        $isHT = in_array($statusShort, ['HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT']) || $isFinished;

        $settlementStatus = 'pending';
        $finalResultString = "$homeGoals-$awayGoals";

        // 1. First Half Markets (HT)
        if (strpos($market, 'primo tempo') !== false || strpos($market, '1h') !== false || strpos($market, 'halftime') !== false) {
            if (!$isHT) return false; // Not reached half time yet

            $homeGoals = $matchData['score']['halftime']['home'] ?? $homeGoals;
            $awayGoals = $matchData['score']['halftime']['away'] ?? $awayGoals;
            $finalResultString = "HT: $homeGoals-$awayGoals";

            // For HT bets, if we are at HT or later, we can settle
            $settlementStatus = $this->calculateResult($market, $homeGoals, $awayGoals);
        }
        // 2. Full Time Markets
        else {
            if (!$isFinished) {
                // Only settle if the result is guaranteed and cannot change
                if (strpos($market, 'over') !== false) {
                    preg_match('/over (\d+\.?\d*)/', $market, $matches);
                    $threshold = (float)($matches[1] ?? 0.5);
                    if (($homeGoals + $awayGoals) > $threshold) $settlementStatus = 'won';
                } elseif ($market == 'gg' || strpos($market, 'both teams to score') !== false || strpos($market, 'goal/goal') !== false) {
                    if ($homeGoals > 0 && $awayGoals > 0) $settlementStatus = 'won';
                }

                if ($settlementStatus === 'pending') return false;
            } else {
                $settlementStatus = $this->calculateResult($market, $homeGoals, $awayGoals);
            }
        }

        if ($settlementStatus !== 'pending') {
            $this->betModel->updateStatus($bet['id'], $settlementStatus, $finalResultString);
            return true;
        }

        return false;
    }

    private function calculateResult($market, $homeGoals, $awayGoals)
    {
        $status = 'lost';
        $total = $homeGoals + $awayGoals;

        // 1X2
        if ($market == '1' || strpos($market, 'home') !== false || strpos($market, 'vittoria casa') !== false) {
            if ($homeGoals > $awayGoals) $status = 'won';
        } elseif ($market == '2' || strpos($market, 'away') !== false || strpos($market, 'vittoria ospite') !== false) {
            if ($awayGoals > $homeGoals) $status = 'won';
        } elseif ($market == 'x' || strpos($market, 'draw') !== false || strpos($market, 'pareggio') !== false) {
            if ($homeGoals == $awayGoals) $status = 'won';
        }
        // Double Chance
        elseif ($market == '1x' || strpos($market, 'double chance 1x') !== false || strpos($market, 'home or draw') !== false) {
            if ($homeGoals >= $awayGoals) $status = 'won';
        } elseif ($market == 'x2' || strpos($market, 'double chance x2') !== false || strpos($market, 'draw or away') !== false) {
            if ($awayGoals >= $homeGoals) $status = 'won';
        } elseif ($market == '12' || strpos($market, 'double chance 12') !== false || strpos($market, 'home or away') !== false) {
            if ($homeGoals != $awayGoals) $status = 'won';
        }
        // Over/Under
        elseif (strpos($market, 'over') !== false) {
            preg_match('/over (\d+\.?\d*)/', $market, $matches);
            $threshold = $matches[1] ?? 0.5;
            if ($total > (float)$threshold) $status = 'won';
        } elseif (strpos($market, 'under') !== false) {
            preg_match('/under (\d+\.?\d*)/', $market, $matches);
            $threshold = $matches[1] ?? 0.5;
            if ($total < (float)$threshold) $status = 'won';
        }
        // BTS
        elseif ($market == 'gg' || strpos($market, 'both teams to score') !== false || strpos($market, 'goal/goal') !== false) {
            if ($homeGoals > 0 && $awayGoals > 0) $status = 'won';
        } elseif ($market == 'ng' || strpos($market, 'no goal') !== false) {
            if ($homeGoals == 0 || $awayGoals == 0) $status = 'won';
        }
        else {
            // Default to pending if market not recognized
            return 'pending';
        }

        return $status;
    }
}

<?php
// app/Services/BetSettler.php

namespace App\Services;

use App\Models\Bet;

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

        if (empty($pending))
            return 0;

        $settledCount = 0;
        foreach ($pending as $bet) {
            $match = null;
            foreach ($liveMatches as $m) {
                if ($m['fixture']['id'] == $bet['fixture_id']) {
                    $match = $m;
                    break;
                }
            }

            if ($match) {
                $status = $match['fixture']['status']['short'];
                // FT = Finished, AET = After Extra Time, PEN = Penalty
                if (in_array($status, ['FT', 'AET', 'PEN'])) {
                    $this->processSettlement($bet, $match);
                    $settledCount++;
                }
            }
        }
        return $settledCount;
    }

    public function processSettlement($bet, $matchData)
    {
        $homeGoals = $matchData['goals']['home'];
        $awayGoals = $matchData['goals']['away'];
        $market = strtolower($bet['market']);

        $status = 'lost'; // Default to lost, then check for win conditions

        // 1. Check for 1X2 market
        if ($market == '1' || strpos($market, 'vittoria casa') !== false || strpos($market, 'vittoria team 1') !== false) {
            if ($homeGoals > $awayGoals)
                $status = 'won';
        } elseif ($market == '2' || strpos($market, 'vittoria ospite') !== false || strpos($market, 'vittoria team 2') !== false) {
            if ($awayGoals > $homeGoals)
                $status = 'won';
        } elseif ($market == 'x' || strpos($market, 'pareggio') !== false) {
            if ($homeGoals == $awayGoals)
                $status = 'won';
        }
        // 2. Over/Under
        elseif (strpos($market, 'over') !== false) {
            preg_match('/over (\d+\.?\d*)/', $market, $matches);
            $threshold = $matches[1] ?? 0.5;
            if (($homeGoals + $awayGoals) > (float) $threshold)
                $status = 'won';
        } elseif (strpos($market, 'under') !== false) {
            preg_match('/under (\d+\.?\d*)/', $market, $matches);
            $threshold = $matches[1] ?? 0.5;
            if (($homeGoals + $awayGoals) < (float) $threshold)
                $status = 'won';
        }
        // 3. 1X / X2 (Double Chance)
        elseif ($market == '1x') {
            if ($homeGoals >= $awayGoals)
                $status = 'won';
        } elseif ($market == 'x2') {
            if ($awayGoals >= $homeGoals)
                $status = 'won';
        }
        // 4. GG / NG (Both Teams To Score)
        elseif ($market == 'gg' || strpos($market, 'goal/goal') !== false) {
            if ($homeGoals > 0 && $awayGoals > 0)
                $status = 'won';
        } elseif ($market == 'ng' || strpos($market, 'no goal') !== false) {
            if ($homeGoals == 0 || $awayGoals == 0)
                $status = 'won';
        }
        // 5. Next Goal (Prossimo Goal)
        elseif (strpos($market, 'prossimo goal') !== false) {
            // This is harder to settle from just the final score without event history
            // But if we know the score, we can at least try.
            // For now, let's mark it as lost if we can't be sure, or manual
            $status = 'pending';
        }

        if ($status !== 'pending') {
            $this->betModel->updateStatus($bet['id'], $status, "$homeGoals-$awayGoals");
        }
    }
}

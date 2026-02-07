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

        if (empty($pending))
            return 0;

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
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM bets WHERE status = 'pending'");
        $pending = $stmt->fetchAll();

        if (empty($pending))
            return 0;

        $settledCount = 0;

        foreach ($pending as $bet) {
            $stmt = $db->prepare("SELECT f.*, t1.name as home_name, t2.name as away_name 
                                 FROM fixtures f 
                                 JOIN teams t1 ON f.team_home_id = t1.id 
                                 JOIN teams t2 ON f.team_away_id = t2.id 
                                 WHERE f.id = ?");
            $stmt->execute([$bet['fixture_id']]);
            $fixture = $stmt->fetch();

            if ($fixture) {
                // Mapping DB fields to expected match data structure
                $matchData = [
                    'fixture' => ['status' => ['short' => $fixture['status_short']]],
                    'teams' => [
                        'home' => ['name' => $fixture['home_name']],
                        'away' => ['name' => $fixture['away_name']]
                    ],
                    'goals' => ['home' => (int) $fixture['score_home'], 'away' => (int) $fixture['score_away']],
                    'score' => [
                        'halftime' => [
                            'home' => ($fixture['score_home_ht'] !== null) ? (int) $fixture['score_home_ht'] : null,
                            'away' => ($fixture['score_away_ht'] !== null) ? (int) $fixture['score_away_ht'] : null
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
        $market = mb_strtolower($bet['market'], 'UTF-8');
        $advice = mb_strtolower($bet['advice'], 'UTF-8');
        $searchString = $market . ' ' . $advice;

        // Handle Void Matches
        if (in_array($statusShort, ['PST', 'CANC', 'ABD', 'AWD', 'WO'])) {
            $this->betModel->updateStatus($bet['id'], 'void', "Match $statusShort");
            return true;
        }

        $isFinished = in_array($statusShort, ['FT', 'AET', 'PEN']);
        $isHT = in_array($statusShort, ['HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT']) || $isFinished;

        $settlementStatus = 'pending';
        $finalResultString = "$homeGoals-$awayGoals";

        // 1. First Half Markets (HT)
        if (strpos($searchString, 'primo tempo') !== false || strpos($searchString, '1h') !== false || strpos($searchString, 'halftime') !== false || strpos($searchString, '1° tempo') !== false) {
            if (!$isHT)
                return false; // Not reached half time yet

            $homeGoals = $matchData['score']['halftime']['home'] ?? $homeGoals;
            $awayGoals = $matchData['score']['halftime']['away'] ?? $awayGoals;
            $finalResultString = "HT: $homeGoals-$awayGoals";

            $settlementStatus = $this->calculateResult($bet, $matchData, $homeGoals, $awayGoals);
        }
        // 2. Full Time Markets
        else {
            if (!$isFinished) {
                // Only settle if the result is guaranteed and cannot change
                if (strpos($searchString, 'over') !== false) {
                    preg_match('/over\s+(\d+\.?\d*)/', $searchString, $matches);
                    $threshold = (float) ($matches[1] ?? 0.5);
                    if (($homeGoals + $awayGoals) > $threshold)
                        $settlementStatus = 'won';
                } elseif (strpos($searchString, 'gg') !== false || strpos($searchString, 'both teams to score') !== false || strpos($searchString, 'goal/goal') !== false || strpos($searchString, 'entrambe') !== false) {
                    if ($homeGoals > 0 && $awayGoals > 0)
                        $settlementStatus = 'won';
                }

                if ($settlementStatus === 'pending')
                    return false;
            } else {
                $settlementStatus = $this->calculateResult($bet, $matchData, $homeGoals, $awayGoals);
            }
        }

        if ($settlementStatus !== 'pending') {
            $this->betModel->updateStatus($bet['id'], $settlementStatus, $finalResultString);
            return true;
        }

        return false;
    }

    private function calculateResult($bet, $matchData, $homeGoals, $awayGoals)
    {
        $market = mb_strtolower($bet['market'], 'UTF-8');
        $advice = mb_strtolower($bet['advice'], 'UTF-8');
        $searchString = $market . ' ' . $advice;

        $homeName = mb_strtolower($matchData['teams']['home']['name'], 'UTF-8');
        $awayName = mb_strtolower($matchData['teams']['away']['name'], 'UTF-8');

        $status = 'lost';
        $total = $homeGoals + $awayGoals;

        // --- 1X2 / Match Winner ---
        $isHomeWin = ($homeGoals > $awayGoals);
        $isAwayWin = ($awayGoals > $homeGoals);
        $isDraw = ($homeGoals == $awayGoals);

        // Home Win Patterns
        if (
            $market === '1' || strpos($searchString, 'vittoria casa') !== false || strpos($searchString, 'home win') !== false ||
            ($homeName && strpos($searchString, $homeName) !== false && (strpos($searchString, 'win') !== false || strpos($searchString, 'vincera') !== false || strpos($searchString, 'vincerà') !== false || strpos($searchString, ' vittoria') !== false) && strpos($searchString, 'draw') === false && strpos($searchString, 'pareggio') === false)
        ) {
            if ($isHomeWin)
                return 'won';
        }
        // Away Win Patterns
        elseif (
            $market === '2' || strpos($searchString, 'vittoria ospite') !== false || strpos($searchString, 'away win') !== false ||
            ($awayName && strpos($searchString, $awayName) !== false && (strpos($searchString, 'win') !== false || strpos($searchString, 'vincera') !== false || strpos($searchString, 'vincerà') !== false || strpos($searchString, ' vittoria') !== false) && strpos($searchString, 'draw') === false && strpos($searchString, 'pareggio') === false)
        ) {
            if ($isAwayWin)
                return 'won';
        }
        // Draw Patterns
        elseif ($market === 'x' || strpos($searchString, 'pareggio') !== false || (strpos($searchString, 'draw') !== false && strpos($searchString, 'home') === false && strpos($searchString, 'away') === false && strpos($searchString, 'double') === false)) {
            if ($isDraw)
                return 'won';
        }

        // --- Double Chance ---
        if (strpos($searchString, 'double chance') !== false || strpos($searchString, 'doppia chance') !== false || strpos($searchString, ' or ') !== false || strpos($searchString, '/') !== false || strpos($searchString, '1x') !== false || strpos($searchString, 'x2') !== false || strpos($searchString, '12') !== false) {
            $is1X = (strpos($searchString, '1x') !== false || strpos($searchString, 'home or draw') !== false || strpos($searchString, 'casa o pareggio') !== false || (strpos($searchString, $homeName) !== false && strpos($searchString, 'draw') !== false) || (strpos($searchString, $homeName) !== false && strpos($searchString, 'pareggio') !== false));
            $isX2 = (strpos($searchString, 'x2') !== false || strpos($searchString, 'draw or away') !== false || strpos($searchString, 'pareggio o ospite') !== false || (strpos($searchString, $awayName) !== false && strpos($searchString, 'draw') !== false) || (strpos($searchString, $awayName) !== false && strpos($searchString, 'pareggio') !== false));
            $is12 = (strpos($searchString, '12') !== false || strpos($searchString, 'home or away') !== false || strpos($searchString, 'casa o ospite') !== false || (strpos($searchString, $homeName) !== false && strpos($searchString, $awayName) !== false));

            if ($is1X && ($isHomeWin || $isDraw))
                return 'won';
            if ($isX2 && ($isAwayWin || $isDraw))
                return 'won';
            if ($is12 && ($isHomeWin || $isAwayWin))
                return 'won';

            // If it matched the DC pattern but didn't win, it's a loss
            if ($is1X || $isX2 || $is12)
                return 'lost';
        }

        // --- Over/Under ---
        if (strpos($searchString, 'over') !== false) {
            preg_match('/over\s+(\d+\.?\d*)/', $searchString, $matches);
            $threshold = (float) ($matches[1] ?? 0.5);
            if ($total > $threshold)
                return 'won';
            return 'lost';
        } elseif (strpos($searchString, 'under') !== false) {
            preg_match('/under\s+(\d+\.?\d*)/', $searchString, $matches);
            $threshold = (float) ($matches[1] ?? 0.5);
            if ($total < $threshold)
                return 'won';
            return 'lost';
        }

        // --- BTS ---
        if (strpos($searchString, 'gg') !== false || strpos($searchString, 'both teams to score') !== false || strpos($searchString, 'goal/goal') !== false || strpos($searchString, 'entrambe') !== false || (strpos($searchString, 'si') !== false && strpos($searchString, 'gol') !== false)) {
            if ($homeGoals > 0 && $awayGoals > 0)
                return 'won';
            return 'lost';
        } elseif (strpos($searchString, 'ng') !== false || strpos($searchString, 'no goal') !== false || (strpos($searchString, 'no') !== false && strpos($searchString, 'gol') !== false)) {
            if ($homeGoals == 0 || $awayGoals == 0)
                return 'won';
            return 'lost';
        }

        return 'pending';
    }
}

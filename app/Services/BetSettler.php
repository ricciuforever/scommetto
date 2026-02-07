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
        $result = $this->settleFromDatabaseDebug();
        return $result['count'];
    }

    public function settleFromDatabaseDebug()
    {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT b.*, f.status_short, f.score_home, f.score_away, 
                       f.score_home_ht, f.score_away_ht,
                       t1.name as home_name, t2.name as away_name 
                FROM bets b 
                JOIN fixtures f ON b.fixture_id = f.id 
                JOIN teams t1 ON f.team_home_id = t1.id 
                JOIN teams t2 ON f.team_away_id = t2.id 
                WHERE b.status = 'pending'";

        $pending = $db->query($sql)->fetchAll();
        $failures = [];

        if (empty($pending))
            return ['count' => 0, 'failures' => []];

        $settledCount = 0;
        foreach ($pending as $bet) {
            $matchData = [
                'fixture' => ['status' => ['short' => $bet['status_short']]],
                'teams' => [
                    'home' => ['name' => $bet['home_name']],
                    'away' => ['name' => $bet['away_name']]
                ],
                'goals' => ['home' => (int) $bet['score_home'], 'away' => (int) $bet['score_away']],
                'score' => [
                    'halftime' => [
                        'home' => ($bet['score_home_ht'] !== null) ? (int) $bet['score_home_ht'] : null,
                        'away' => ($bet['score_away_ht'] !== null) ? (int) $bet['score_away_ht'] : null
                    ]
                ]
            ];

            if ($this->processSettlement($bet, $matchData)) {
                $settledCount++;
            } else {
                $failures[] = [
                    'id' => $bet['id'],
                    'status_short' => $bet['status_short'],
                    'market' => $bet['market'],
                    'home_name' => $bet['home_name'],
                    'away_name' => $bet['away_name'],
                    'score' => $bet['score_home'] . '-' . $bet['score_away']
                ];
            }
        }
        return ['count' => $settledCount, 'failures' => $failures];
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

            $settlementStatus = $this->calculateResult($bet, $matchData, $homeGoals, $awayGoals, true);
        }
        // 2. Full Time Markets
        else {
            if (!$isFinished) {
                // Only settle if the result is guaranteed and cannot change
                if (strpos($searchString, 'over') !== false) {
                    preg_match('/over\s*(\d+\.?\d*)/', $searchString, $matches);
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
                $settlementStatus = $this->calculateResult($bet, $matchData, $homeGoals, $awayGoals, $isFinished);
            }
        }

        if ($settlementStatus !== 'pending') {
            $this->betModel->updateStatus($bet['id'], $settlementStatus, $finalResultString);
            return true;
        }

        return false;
    }

    private function calculateResult($bet, $matchData, $homeGoals, $awayGoals, $isFinished)
    {
        $market = mb_strtolower($bet['market'], 'UTF-8');
        $advice = mb_strtolower($bet['advice'], 'UTF-8');
        $searchString = $market . ' ' . $advice;

        $homeName = mb_strtolower($matchData['teams']['home']['name'], 'UTF-8');
        $awayName = mb_strtolower($matchData['teams']['away']['name'], 'UTF-8');

        $status = 'lost';
        $total = $homeGoals + $awayGoals;

        // --- Helper for fuzzy team matching ---
        $isHomeMentioned = $this->isTeamMentioned($homeName, $searchString);
        $isAwayMentioned = $this->isTeamMentioned($awayName, $searchString);

        // --- 1X2 / Match Winner ---
        $isHomeWin = ($homeGoals > $awayGoals);
        $isAwayWin = ($awayGoals > $homeGoals);
        $isDraw = ($homeGoals == $awayGoals);

        // Home Win Patterns
        if (
            $market === '1' || strpos($searchString, 'vittoria casa') !== false || strpos($searchString, 'home win') !== false ||
            ($isHomeMentioned && (strpos($searchString, 'win') !== false || strpos($searchString, 'vincera') !== false || strpos($searchString, 'vincerà') !== false || strpos($searchString, 'vittoria') !== false || strpos($searchString, 'vincente') !== false) && strpos($searchString, 'draw') === false && strpos($searchString, 'pareggio') === false)
        ) {
            if ($isHomeWin)
                return 'won';
            // Se abbiamo individuato la giocata 1 ma il risultato non è 1, è persa (se il match è finito)
            if ($isFinished && !$isHomeWin)
                return 'lost';
        }
        // Away Win Patterns
        elseif (
            $market === '2' || strpos($searchString, 'vittoria ospite') !== false || strpos($searchString, 'away win') !== false ||
            ($isAwayMentioned && (strpos($searchString, 'win') !== false || strpos($searchString, 'vincera') !== false || strpos($searchString, 'vincerà') !== false || strpos($searchString, 'vittoria') !== false || strpos($searchString, 'vincente') !== false) && strpos($searchString, 'draw') === false && strpos($searchString, 'pareggio') === false)
        ) {
            if ($isAwayWin)
                return 'won';
            if ($isFinished && !$isAwayWin)
                return 'lost';
        }
        // Draw Patterns
        elseif ($market === 'x' || strpos($searchString, 'pareggio') !== false || (strpos($searchString, 'draw') !== false && strpos($searchString, 'home') === false && strpos($searchString, 'away') === false && strpos($searchString, 'double') === false && strpos($searchString, 'no bet') === false)) {
            if ($isDraw) {
                return 'won';
            }
            if ($isFinished && !$isDraw)
                return 'lost';
        }

        // --- Double Chance ---
        $isDC = (
            strpos($searchString, 'double chance') !== false ||
            strpos($searchString, 'doppia chance') !== false ||
            strpos($searchString, ' or ') !== false ||
            strpos($searchString, '/') !== false ||
            strpos($searchString, '1x') !== false ||
            strpos($searchString, 'x2') !== false ||
            strpos($searchString, '12') !== false ||
            strpos($searchString, 'dc') !== false ||
            preg_match('/\b(1x|x2|12)\b/', $searchString)
        );

        if ($isDC) {
            $is1X = (strpos($searchString, '1x') !== false || strpos($searchString, '1/x') !== false || strpos($searchString, 'home or draw') !== false || strpos($searchString, 'casa o pareggio') !== false || ($isHomeMentioned && (strpos($searchString, 'draw') !== false || strpos($searchString, 'pareggio') !== false)));
            $isX2 = (strpos($searchString, 'x2') !== false || strpos($searchString, 'x/2') !== false || strpos($searchString, 'draw or away') !== false || strpos($searchString, 'pareggio o ospite') !== false || ($isAwayMentioned && (strpos($searchString, 'draw') !== false || strpos($searchString, 'pareggio') !== false)));
            $is12 = (strpos($searchString, '12') !== false || strpos($searchString, '1/2') !== false || strpos($searchString, 'home or away') !== false || strpos($searchString, 'casa o ospite') !== false || ($isHomeMentioned && $isAwayMentioned));

            // Fallback intelligente: se l'analisi menziona solo una squadra in un contesto DC, assumiamo 1X o X2
            if (!$is1X && !$isX2 && !$is12) {
                if ($isHomeMentioned)
                    $is1X = true;
                elseif ($isAwayMentioned)
                    $isX2 = true;
            }

            if ($is1X && ($isHomeWin || $isDraw))
                return 'won';
            if ($isX2 && ($isAwayWin || $isDraw))
                return 'won';
            if ($is12 && ($isHomeWin || $isAwayWin))
                return 'won';

            if ($isFinished && ($is1X || $isX2 || $is12))
                return 'lost';
        }

        // --- Over/Under ---
        if (strpos($searchString, 'over') !== false || strpos($searchString, 'più di') !== false || strpos($market, 'goals') !== false) {
            preg_match('/over\s*(\d+\.?\d*)/', $searchString, $matches);
            if (!isset($matches[1]))
                preg_match('/(\d+\.?\d*)\s*goals/', $searchString, $matches);

            if (isset($matches[1])) {
                $threshold = (float) $matches[1];
                if ($total > $threshold)
                    return 'won';
                if ($isFinished)
                    return 'lost';
            }
        }

        if (strpos($searchString, 'under') !== false || strpos($searchString, 'meno di') !== false) {
            preg_match('/under\s*(\d+\.?\d*)/', $searchString, $matches);
            if (isset($matches[1])) {
                $threshold = (float) $matches[1];
                if ($total < $threshold && $isFinished)
                    return 'won';
                if ($total > $threshold)
                    return 'lost';
            }
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

        // --- Correct Score / Risultato Esatto ---
        if (strpos($searchString, 'score') !== false || strpos($searchString, 'risultato esatto') !== false || preg_match('/\d+[-]\d+/', $searchString)) {
            preg_match('/(\d+)\s*[-]\s*(\d+)/', $searchString, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $predHome = (int) $matches[1];
                $predAway = (int) $matches[2];
                if ($homeGoals === $predHome && $awayGoals === $predAway)
                    return 'won';
                return 'lost';
        }

        // --- Next Goal / Next Goalscorer ---
        if (strpos($searchString, 'next goal') !== false || strpos($searchString, 'goalscorer') !== false || strpos($searchString, 'prossimo goal') !== false || strpos($searchString, 'prossimo gol') !== false) {
            if ($isHomeMentioned && $homeGoals > 0) return 'won';
            if ($isAwayMentioned && $awayGoals > 0) return 'won';
            if ($isFinished) return 'lost';
        }

        // --- Fallback per Totals non standard ---
        if (strpos($searchString, 'total goals') !== false) {
             preg_match('/(\d+\.?\d*)/', $searchString, $matches);
             if (isset($matches[1])) {
                 $threshold = (float) $matches[1];
                 if (strpos($searchString, 'over') !== false || strpos($advice, 'over') !== false) {
                     if ($total > $threshold) return 'won';
                     if ($isFinished) return 'lost';
                 }
             }
        }

        return 'pending';
    }

    /**
     * Helper fuzzy per capire se il nome di una squadra è menzionato nella stringa di ricerca
     */
    private function isTeamMentioned($teamName, $searchString)
    {
        if (!$teamName)
            return false;

        $teamName = mb_strtolower($teamName, 'UTF-8');
        // Pulizia aggressiva del nome team, MA preservando i nomi di città/regione distintivi
        // Rimossi: 'montevideo', 'tijuana', 'puebla' dalla lista di cancellazione
        $cleanName = str_replace(
            ['fc', 'u21', 'u20', 'u19', 'cf', 'club', 'sp.', 'deportivo', 'futbol', 'soccer', 'union', 'athletic', 'atletico', 'city', 'united', 'town', 'real'],
            '',
            $teamName
        );
        // Se la pulizia ha tolto tutto (es. "Club Deportivo"), rimettiamo l'originale
        if (trim($cleanName) === '')
            $cleanName = $teamName;

        $searchString = mb_strtolower($searchString, 'UTF-8');
        $words = explode(' ', trim($cleanName));

        foreach ($words as $w) {
            $w = trim($w);
            // Ignora parole troppo corte dopo la pulizia (es. "la", "de")
            if (strlen($w) > 2 && strpos($searchString, $w) !== false) {
                return true;
            }
        }
        return false;
    }
}

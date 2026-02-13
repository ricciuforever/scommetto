<?php
// app/Services/IntelligenceService.php

namespace App\Services;

use App\Models\Fixture;
use App\Models\TeamStats;
use App\Models\Standing;
use App\Models\Prediction;

class IntelligenceService
{
    private $fixtureModel;
    private $statsModel;
    private $standingModel;
    private $predictionModel;
    private $h2hModel;
    private $topStatsModel;

    public function __construct()
    {
        $this->fixtureModel = new Fixture();
        $this->statsModel = new TeamStats();
        $this->standingModel = new Standing();
        $this->predictionModel = new Prediction();
        $this->h2hModel = new \App\Models\H2H();
        $this->topStatsModel = new \App\Models\TopStats();
    }

    /**
     * Gathers all local data for a match to provide deep context to Gemini
     */
    public function getDeepContext($fixture_id, $home_id, $away_id, $league_id, $season = null)
    {
        if ($season === null) {
            $season = \App\Config\Config::getCurrentSeason();
        }

        $context = [
            'home' => [
                'recent_matches' => $this->fixtureModel->getTeamRecent($home_id),
                'stats' => $this->statsModel->get($home_id, $league_id, $season),
                'standing' => $this->standingModel->getByTeamAndLeague($home_id, $league_id)
            ],
            'away' => [
                'recent_matches' => $this->fixtureModel->getTeamRecent($away_id),
                'stats' => $this->statsModel->get($away_id, $league_id, $season),
                'standing' => $this->standingModel->getByTeamAndLeague($away_id, $league_id)
            ],
            'h2h' => $this->h2hModel->get($home_id, $away_id),
            'league_top_stats' => [
                'scorers' => $this->topStatsModel->get($league_id, $season, 'scorers'),
                'assists' => $this->topStatsModel->get($league_id, $season, 'assists')
            ],
            'api_prediction' => $this->predictionModel->getByFixtureId($fixture_id)
        ];

        return $context;
    }

    /**
     * Recupera il contesto storico di performance (Memoria di GiaNik)
     */
    public function getPerformanceContext($homeTeam, $awayTeam, $marketType, $leagueName = null, $leagueId = null)
    {
        $db = \App\GiaNik\GiaNikDatabase::getInstance()->getConnection();

        $sql = "SELECT * FROM performance_metrics
                WHERE (context_type = 'MARKET' AND context_id = ?)
                   OR (context_type = 'TEAM' AND context_id IN (?, ?))";

        $execParams = [$marketType, strtoupper($homeTeam), strtoupper($awayTeam)];

        if ($leagueName || $leagueId) {
            $sql .= " OR (context_type = 'LEAGUE' AND context_id IN (";
            $leagueParams = [];
            if ($leagueName) $leagueParams[] = strtoupper((string)$leagueName);
            if ($leagueId) $leagueParams[] = strtoupper((string)$leagueId);

            $sql .= implode(',', array_fill(0, count($leagueParams), '?')) . "))";
            $execParams = array_merge($execParams, $leagueParams);
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Formattiamo per il prompt
        $text = "MEMORIA STORICA GIANIK:\n";
        $hasData = false;

        foreach ($rows as $r) {
            $hasData = true;
            $sign = $r['roi'] > 0 ? '+' : '';
            $text .= "- {$r['context_type']} {$r['context_id']}: ROI {$sign}{$r['roi']}% su {$r['total_bets']} bets.\n";
        }

        return $hasData ? $text : "Nessuno storico significativo rilevato per questo match.";
    }

    /**
     * Apprende da una scommessa singola (Real-time Learning)
     */
    public function learnFromBet($bet)
    {
        // Evita doppi conteggi
        if (!empty($bet['is_learned'])) {
            return;
        }

        // Soccer-only restriction for GiaNik memory
        $sport = strtoupper($bet['sport'] ?? '');
        if ($sport !== 'SOCCER' && $sport !== 'FOOTBALL' && !empty($sport)) {
            return;
        }

        $db = \App\GiaNik\GiaNikDatabase::getInstance()->getConnection();

        $isWin = (float)($bet['profit'] ?? 0) > 0 || (isset($bet['status']) && $bet['status'] === 'won');
        $netProfit = (float)($bet['profit'] ?? 0) - (float)($bet['commission'] ?? 0);

        // Se persa, il profitto deve essere negativo pari allo stake se non già impostato correttamente
        if (isset($bet['status']) && $bet['status'] === 'lost' && $netProfit >= 0) {
            $netProfit = -(float)$bet['stake'];
        }

        $stake = (float)$bet['stake'];

        $marketNameNormalized = $this->normalizeMarketName($bet['market_name'] ?? '');
        $teams = $this->parseTeams($bet['event_name'] ?? '');

        $metricsToUpdate = [
            ['type' => 'MARKET', 'id' => $marketNameNormalized],
        ];

        if ($teams['home']) $metricsToUpdate[] = ['type' => 'TEAM', 'id' => $teams['home']];
        if ($teams['away']) $metricsToUpdate[] = ['type' => 'TEAM', 'id' => $teams['away']];

        $leagueId = !empty($bet['league_id']) ? $bet['league_id'] : null;
        $leagueName = $bet['league'] ?? $bet['competition'] ?? 'UNKNOWN';

        // Tenta di recuperare la lega dal market name o event name se sconosciuta
        if ($leagueName === 'UNKNOWN' && !empty($bet['market_name'])) {
            // Regex più robusta: cerca parole prima di parole chiave come UNDER, OVER, ESITO, BTTS, MATCH, 1X2, DRAW
            // gestisce anche casi attaccati come INTERU20UNDER
            if (preg_match('/^([A-Z0-9 ]+?)(?:UNDER|OVER|ESITO|BTTS|MATCH|1X2|DRAW)/i', $bet['market_name'], $m)) {
                $leagueName = trim($m[1]);
            }
        }

        if ($leagueId) {
            $metricsToUpdate[] = ['type' => 'LEAGUE', 'id' => $leagueId];
        } elseif ($leagueName !== 'UNKNOWN') {
            $metricsToUpdate[] = ['type' => 'LEAGUE', 'id' => $leagueName];
        }

        // Performance by Odds Bucket
        $bucket = $this->getOddsBucket($bet['odds'] ?? 0);
        $metricsToUpdate[] = ['type' => 'BUCKET', 'id' => $bucket];

        foreach ($metricsToUpdate as $m) {
            $contextId = strtoupper(trim($m['id']));
            $this->updateMetric($db, $m['type'], $contextId, $isWin, $netProfit, $stake);
        }

        // Segna come appresa nel DB locale
        if (!empty($bet['id'])) {
            $db->prepare("UPDATE bets SET is_learned = 1 WHERE id = ?")->execute([$bet['id']]);
        }
    }

    private function updateMetric($db, $type, $id, $isWin, $profit, $stake)
    {
        $roi = ($stake > 0) ? round(($profit / $stake) * 100, 2) : 0;

        $sql = "INSERT INTO performance_metrics
                (context_type, context_id, total_bets, wins, losses, total_stake, profit_loss, roi, last_updated)
                VALUES (?, ?, 1, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(context_type, context_id) DO UPDATE SET
                total_bets = total_bets + 1,
                wins = wins + ?,
                losses = losses + ?,
                total_stake = total_stake + ?,
                profit_loss = profit_loss + ?,
                roi = ROUND(((profit_loss + ?) / (total_stake + ?)) * 100, 2),
                last_updated = CURRENT_TIMESTAMP";

        $wins = $isWin ? 1 : 0;
        $losses = $isWin ? 0 : 1;

        $db->prepare($sql)->execute([
            $type, $id, $wins, $losses, $stake, $profit, $roi,
            $wins, $losses, $stake, $profit, $profit, $stake
        ]);
    }

    public function normalizeMarketName($marketName)
    {
        $m = strtoupper($marketName);

        if (strpos($m, 'MATCH ODDS') !== false || strpos($m, 'ESITO FINALE') !== false || strpos($m, '1X2') !== false)
            return 'MATCH ODDS';

        if (strpos($m, 'OVER/UNDER') !== false || strpos($m, 'GOL') !== false || strpos($m, 'GOAL') !== false || strpos($m, 'OVER') !== false || strpos($m, 'UNDER') !== false) {
            if (preg_match('/(?:OVER|UNDER|GOL|GOAL)\s*(\d+\.?\d*)/', $m, $matches)) {
                $line = $matches[1];
                // Gestione formati come "25" -> "2.5"
                if (strlen($line) == 2 && $line[0] != '0') $line = $line[0] . "." . $line[1];
                elseif (strlen($line) == 2 && $line[0] == '0') $line = "0." . $line[1];
                return "OVER/UNDER " . $line . " GOALS";
            }
            return 'OVER/UNDER GOALS';
        }

        if (strpos($m, 'BOTH TEAMS TO SCORE') !== false || strpos($m, 'GOAL/NO GOAL') !== false || strpos($m, 'BTTS') !== false || strpos($m, 'ENTRAMBE LE SQUADRE A SEGNO') !== false)
            return 'BOTH TEAMS TO SCORE';

        if (strpos($m, 'CORRECT SCORE') !== false || strpos($m, 'RISULTATO ESATTO') !== false)
            return 'CORRECT SCORE';

        if (strpos($m, 'DOUBLE CHANCE') !== false || strpos($m, 'DOPPIA CHANCE') !== false)
            return 'DOUBLE CHANCE';

        if (strpos($m, 'DRAW NO BET') !== false || strpos($m, 'RIMBORSO IN CASO DI PAREGGIO') !== false)
            return 'DRAW NO BET';

        if (strpos($m, 'HALF TIME') !== false || strpos($m, 'PRIMO TEMPO') !== false)
            return 'HALF TIME';

        // Fallback per nomi sporchi CSV e altri sport
        if (strpos($m, 'ESITOFINALE') !== false)
            return 'MATCH ODDS';

        if (strpos($m, 'VINCENTE INCONTRO') !== false || strpos($m, 'MONEYLINE') !== false)
            return 'MONEYLINE';

        return preg_replace('/[^A-Z0-9 ]/', '', $m);
    }

    public function parseMarketType($marketName)
    {
        return $this->normalizeMarketName($marketName);
    }

    public function parseTeams($eventName)
    {
        $res = ['home' => null, 'away' => null];
        $parts = preg_split('/(\s-\s|\sv\s|\svs\s)/i', $eventName);
        if (count($parts) >= 2) {
            $res['home'] = trim($parts[0]);
            $res['away'] = trim($parts[1]);
        }
        return $res;
    }

    public function getOddsBucket($odds)
    {
        if ($odds <= 1.50) return 'FAV';
        if ($odds <= 2.20) return 'VAL';
        return 'RISK';
    }
}

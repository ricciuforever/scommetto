<?php
// app/GiaNik/Controllers/BrainController.php

namespace App\GiaNik\Controllers;

use App\GiaNik\GiaNikDatabase;
use PDO;

class BrainController
{
    private $db;
    private $coreDb;

    public function __construct()
    {
        $this->db = GiaNikDatabase::getInstance()->getConnection();

        // Connessione al DB Core per i loghi
        $possiblePaths = [
            __DIR__ . '/../../../data/database.sqlite',
            __DIR__ . '/../../../database.sqlite',
            __DIR__ . '/../../../data/scommetto.sqlite'
        ];
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                try {
                    $this->coreDb = new PDO('sqlite:' . $path);
                    if ($this->coreDb) break;
                } catch (\Exception $e) {}
            }
        }
    }

    public function index()
    {
        // 1. Metriche Globali (Filtrate per MARKET per evitare double-counting)
        $global = $this->db->query("
            SELECT
                SUM(total_bets) as total_bets,
                SUM(wins) as wins,
                SUM(losses) as losses,
                SUM(profit_loss) as total_profit,
                SUM(total_stake) as total_stake
            FROM performance_metrics
            WHERE context_type = 'MARKET'
        ")->fetch(PDO::FETCH_ASSOC);

        // Se non ci sono dati, inizializza a zero
        if (!$global || !$global['total_bets']) {
            $global = [
                'total_bets' => 0,
                'wins' => 0,
                'losses' => 0,
                'total_profit' => 0,
                'total_stake' => 0
            ];
        }

        // Calcolo ROI e WinRate globale
        $global['roi'] = ($global['total_stake'] > 0) ? ($global['total_profit'] / $global['total_stake']) * 100 : 0;
        $global['win_rate'] = ($global['total_bets'] > 0) ? ($global['wins'] / $global['total_bets']) * 100 : 0;

        // 2. Blacklist (ROI < -15% e almeno 5 bets)
        $blacklist = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE roi < -15 AND total_bets >= 5
            ORDER BY roi ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Top Leghe (Ordinate per profitto)
        $topLeagues = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE context_type = 'LEAGUE'
            ORDER BY profit_loss DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Top Squadre (Ordinate per profitto)
        $topTeams = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE context_type = 'TEAM'
            ORDER BY profit_loss DESC LIMIT 12
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 5. Metriche per Odds Bucket
        $buckets = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE context_type = 'BUCKET'
            ORDER BY CASE context_id
                WHEN 'FAV' THEN 1
                WHEN 'VAL' THEN 2
                WHEN 'RISK' THEN 3
                ELSE 4 END
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Arricchimento Nomi (per Leghe)
        foreach ($topLeagues as &$league) {
            if (is_numeric($league['context_id'])) {
                $league['display_name'] = $this->getLeagueName($league['context_id']);
            } else {
                $league['display_name'] = $league['context_id'];
            }
        }

        // Arricchimento Loghi (solo per Teams)
        foreach ($topTeams as &$team) {
            $team['logo'] = $this->getTeamLogo($team['context_id']);
        }

        // 6. AI Lessons
        $lessons = [];
        try {
            $lessons = $this->db->query("SELECT * FROM ai_lessons ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Tabella non esiste ancora o errore, ignoriamo
        }

        // 7. Last Learning Date
        $lastUpdate = $this->db->query("SELECT MAX(last_updated) FROM performance_metrics")->fetchColumn();

        // Load View
        require __DIR__ . '/../Views/brain.php';
    }

    public function rebuild()
    {
        set_time_limit(300);

        // 0. Recupero Dati mancanti (Retro-compatibilità)
        $this->repairMissingLeagues();

        // 1. Svuota performance_metrics
        $this->db->exec("DELETE FROM performance_metrics");
        // 2. Resetta is_learned su tutte le scommesse
        $this->db->exec("UPDATE bets SET is_learned = 0");

        // 3. Recupera tutte le scommesse settled
        $stmt = $this->db->query("SELECT * FROM bets WHERE status IN ('won', 'lost')");
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $intelligence = new \App\Services\IntelligenceService();
        $count = 0;
        foreach ($bets as $bet) {
            $intelligence->learnFromBet($bet);
            $count++;
        }

        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'rebuilt_bets' => $count]);
            exit;
        } else {
            echo "✅ Brain rebuilt with $count bets.\n";
        }
    }

    private function repairMissingLeagues()
    {
        if (!$this->coreDb) return;

        // Recupera tutte le scommesse reali con league_id mancante o league 'UNKNOWN' / 'IMPORTED CSV'
        $stmt = $this->db->query("SELECT id, betfair_id, event_name, market_name, league FROM bets WHERE (league_id IS NULL OR league IN ('UNKNOWN', 'IMPORTED CSV')) AND betfair_id IS NOT NULL");
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bets as $bet) {
            $lid = null;
            $lname = null;

            // 1. Tenta estrazione da Market Name (se sporco)
            if (!empty($bet['market_name'])) {
                if (preg_match('/^([A-Z0-9 ]+?)(?:UNDER|OVER|ESITO|BTTS|MATCH|1X2|DRAW)/i', $bet['market_name'], $m)) {
                    $lname = trim($m[1]);
                }
            }

            // 2. Tenta Match Name in Fixtures Core
            try {
                $teams = preg_split('/\s+(v|vs|-)\s+/i', $bet['event_name']);
                if (count($teams) >= 1) {
                    $home = trim($teams[0]);
                    $away = $teams[1] ?? null;

                    $stmtLeague = $this->coreDb->prepare("
                        SELECT f.league_id FROM fixtures f
                        WHERE (f.team_home_name LIKE ? OR f.team_away_name LIKE ? OR f.team_home_name LIKE ? OR f.team_away_name LIKE ?)
                        ORDER BY f.date DESC LIMIT 1
                    ");
                    $searchHome = '%' . $home . '%';
                    $searchAway = $away ? '%' . $away . '%' : $searchHome;

                    $stmtLeague->execute([$searchHome, $searchHome, $searchAway, $searchAway]);
                    $lid = $stmtLeague->fetchColumn();

                    if ($lid) {
                        $lname = $this->getLeagueName($lid);
                    }
                }
            } catch (\Exception $e) {}

            if ($lid || $lname) {
                $this->db->prepare("UPDATE bets SET league_id = ?, league = ? WHERE id = ?")
                         ->execute([$lid, $lname, $bet['id']]);
            }
        }
    }

    private function getLeagueName($leagueId)
    {
        if (!$this->coreDb) return "Lega $leagueId";
        try {
            $stmt = $this->coreDb->prepare("SELECT name FROM leagues WHERE id = ? LIMIT 1");
            $stmt->execute([$leagueId]);
            return $stmt->fetchColumn() ?: "Lega $leagueId";
        } catch (\Exception $e) {
            return "Lega $leagueId";
        }
    }

    private function getTeamLogo($teamName)
    {
        if (!$this->coreDb) return null;
        // Ricerca semplice per nome
        try {
            $stmt = $this->coreDb->prepare("SELECT logo FROM teams WHERE name LIKE ? LIMIT 1");
            $stmt->execute([$teamName]);
            return $stmt->fetchColumn() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

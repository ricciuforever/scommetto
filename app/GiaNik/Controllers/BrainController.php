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

        // 3. Top Leghe (ROI > 0, ordinate per profitto)
        $topLeagues = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE context_type = 'LEAGUE' AND profit_loss > 0
            ORDER BY profit_loss DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Top Squadre (ROI > 0, ordinate per profitto)
        $topTeams = $this->db->query("
            SELECT * FROM performance_metrics
            WHERE context_type = 'TEAM' AND profit_loss > 0
            ORDER BY profit_loss DESC LIMIT 10
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

        // Load View
        require __DIR__ . '/../Views/brain.php';
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

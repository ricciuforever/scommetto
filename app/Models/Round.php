<?php
// app/Models/Round.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Round
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($leagueId, $season, $roundName)
    {
        $sql = "INSERT INTO rounds (league_id, season, round_name) 
                VALUES (:league_id, :season, :round_name)
                ON DUPLICATE KEY UPDATE 
                last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'league_id' => $leagueId,
            'season' => $season,
            'round_name' => $roundName
        ]);
    }

    public function getByLeagueSeason($leagueId, $season)
    {
        $stmt = $this->db->prepare("SELECT round_name FROM rounds WHERE league_id = ? AND season = ? ORDER BY last_updated DESC");
        $stmt->execute([$leagueId, $season]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function needsRefresh($leagueId, $season, $hours = 24)
    {
        // Controlliamo l'ultima sincronizzazione per questa combinazione
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last FROM rounds WHERE league_id = ? AND season = ?");
        $stmt->execute([$leagueId, $season]);
        $row = $stmt->fetch();

        if (!$row || !$row['last'])
            return true;
        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

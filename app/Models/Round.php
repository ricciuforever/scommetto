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
        $stmt = $this->db->prepare("SELECT * FROM rounds WHERE league_id = ? AND season = ? ORDER BY round_name");
        $stmt->execute([$leagueId, $season]);
        return $stmt->fetchAll();
    }
}

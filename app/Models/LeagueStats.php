<?php
// app/Models/LeagueStats.php

namespace App\Models;

use App\Services\Database;

class LeagueStats
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function get($leagueId, $season, $type)
    {
        $stmt = $this->db->prepare("SELECT json_data, last_updated FROM league_topstats WHERE league_id = ? AND season = ? AND type = ?");
        $stmt->execute([$leagueId, $season, $type]);
        $row = $stmt->fetch();

        if ($row) {
            return [
                'data' => json_decode($row['json_data'], true),
                'last_updated' => $row['last_updated']
            ];
        }
        return null;
    }

    public function save($leagueId, $season, $type, $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO league_topstats (league_id, season, type, json_data)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE json_data = VALUES(json_data), last_updated = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$leagueId, $season, $type, json_encode($data)]);
    }

    public function isStale($lastUpdated, $hours = 24)
    {
        if (!$lastUpdated)
            return true;
        return (time() - strtotime($lastUpdated)) > ($hours * 3600);
    }
}

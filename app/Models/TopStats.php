<?php
// app/Models/TopStats.php

namespace App\Models;

use App\Services\Database;
use PDO;

class TopStats
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($leagueId, $season, $type, $statsData)
    {
        $sql = "INSERT INTO top_stats (league_id, season, type, stats_json) 
                VALUES (:league_id, :season, :type, :stats_json)
                ON DUPLICATE KEY UPDATE 
                stats_json = VALUES(stats_json), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'league_id' => $leagueId,
            'season' => $season,
            'type' => $type,
            'stats_json' => json_encode($statsData)
        ]);
    }

    public function get($leagueId, $season, $type)
    {
        $stmt = $this->db->prepare("SELECT * FROM top_stats WHERE league_id = ? AND season = ? AND type = ?");
        $stmt->execute([$leagueId, $season, $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['stats_json'] = json_decode($row['stats_json'], true);
        }
        return $row;
    }
}

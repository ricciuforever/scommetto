<?php
// app/Models/FixturePlayerStatistics.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixturePlayerStatistics
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixtureId, $teamId, $playerId, $stats)
    {
        $sql = "INSERT INTO fixture_player_stats (fixture_id, team_id, player_id, stats_json)
                VALUES (:fid, :tid, :pid, :stats)
                ON DUPLICATE KEY UPDATE
                stats_json = VALUES(stats_json), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixtureId,
            'tid' => $teamId,
            'pid' => $playerId,
            'stats' => json_encode($stats)
        ]);
    }

    public function getByFixture($fixtureId)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_player_stats WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByFixture($fixtureId)
    {
        $stmt = $this->db->prepare("DELETE FROM fixture_player_stats WHERE fixture_id = ?");
        return $stmt->execute([$fixtureId]);
    }
}

<?php
// app/Models/FixtureStatistics.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixtureStatistics
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $team_id, $stats)
    {
        $sql = "INSERT INTO fixture_statistics (fixture_id, team_id, stats_json)
                VALUES (:fid, :tid, :stats)
                ON DUPLICATE KEY UPDATE
                    stats_json = VALUES(stats_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'tid' => $team_id,
            'stats' => json_encode($stats)
        ]);
    }

    public function getByFixture($fixture_id)
    {
        $sql = "SELECT fs.*, t.name as team_name, t.logo as team_logo
                FROM fixture_statistics fs
                JOIN teams t ON fs.team_id = t.id
                WHERE fs.fixture_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fixture_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

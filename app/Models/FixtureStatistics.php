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
                VALUES (:fid, :tid, :stats)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(fixture_id, team_id) DO UPDATE SET
                    stats_json = EXCLUDED.stats_json,
                    last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    stats_json = VALUES(stats_json),
                    last_updated = CURRENT_TIMESTAMP";
        }

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
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['stats_json'] = json_decode($row['stats_json'], true);
        }

        return $results;
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_statistics WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_sync'])
            return true;

        $lastSync = strtotime($row['last_sync']);
        $isLive = in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'P', 'BT']);

        if ($isLive) {
            return (time() - $lastSync) > 60; // 1 minuto se live
        }

        return (time() - $lastSync) > 86400; // 24 ore altrimenti
    }
}

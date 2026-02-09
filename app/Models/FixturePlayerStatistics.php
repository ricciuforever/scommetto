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
        $sql = "SELECT fps.*, p.name as player_name, p.photo as player_photo, t.name as team_name, t.logo as team_logo
                FROM fixture_player_stats fps
                JOIN players p ON fps.player_id = p.id
                JOIN teams t ON fps.team_id = t.id
                WHERE fps.fixture_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fixtureId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['stats_json'] = json_decode($row['stats_json'], true);
        }

        return $results;
    }

    public function deleteByFixture($fixtureId)
    {
        $stmt = $this->db->prepare("DELETE FROM fixture_player_stats WHERE fixture_id = ?");
        return $stmt->execute([$fixtureId]);
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_player_stats WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_sync'])
            return true;

        $lastSync = strtotime($row['last_sync']);
        $isLive = in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'P', 'BT']);

        if ($isLive) {
            return (time() - $lastSync) > 120; // 2 minuti se live (sono dati pesanti)
        }

        return (time() - $lastSync) > 86400; // 24 ore altrimenti
    }
}

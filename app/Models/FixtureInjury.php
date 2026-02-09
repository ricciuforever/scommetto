<?php
// app/Models/FixtureInjury.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixtureInjury
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $data)
    {
        $sql = "INSERT INTO fixture_injuries (fixture_id, team_id, player_id, player_name, type, reason)
                VALUES (:fid, :tid, :pid, :pname, :type, :reason)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'tid' => $data['team']['id'] ?? null,
            'pid' => $data['player']['id'] ?? null,
            'pname' => $data['player']['name'] ?? 'Unknown',
            'type' => $data['player']['type'] ?? 'Injury',
            'reason' => $data['player']['reason'] ?? 'N/A'
        ]);
    }

    public function deleteByFixture($fixture_id)
    {
        $stmt = $this->db->prepare("DELETE FROM fixture_injuries WHERE fixture_id = ?");
        return $stmt->execute([$fixture_id]);
    }

    public function getByFixture($fixture_id)
    {
        $sql = "SELECT fi.*, t.logo as team_logo, t.name as team_name, p.photo as player_photo
                FROM fixture_injuries fi
                JOIN teams t ON fi.team_id = t.id
                LEFT JOIN players p ON fi.player_id = p.id
                WHERE fi.fixture_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fixture_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_injuries WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_sync'])
            return true;

        $lastSync = strtotime($row['last_sync']);

        // Injuries don't change as often as events or stats
        // We can refresh every 4 hours for live/upcoming matches
        $isFinished = in_array($statusShort, ['FT', 'AET', 'PEN']);

        if ($isFinished) {
            return (time() - $lastSync) > 86400; // 24 ore per i terminati
        }

        return (time() - $lastSync) > 14400; // 4 ore altrimenti
    }
}

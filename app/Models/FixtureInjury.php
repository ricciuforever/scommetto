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
            'tid' => $data['team']['id'],
            'pid' => $data['player']['id'],
            'pname' => $data['player']['name'],
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
        $stmt = $this->db->prepare("SELECT * FROM fixture_injuries WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php
// app/Models/FixtureEvent.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixtureEvent
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $data)
    {
        $sql = "INSERT INTO fixture_events (fixture_id, team_id, player_id, assist_id, time_elapsed, time_extra, type, detail, comments)
                VALUES (:fid, :tid, :pid, :aid, :elapsed, :extra, :type, :detail, :comments)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'tid' => $data['team']['id'],
            'pid' => $data['player']['id'] ?? null,
            'aid' => $data['assist']['id'] ?? null,
            'elapsed' => $data['time']['elapsed'],
            'extra' => $data['time']['extra'] ?? null,
            'type' => $data['type'],
            'detail' => $data['detail'],
            'comments' => $data['comments'] ?? null
        ]);
    }

    public function deleteByFixture($fixture_id)
    {
        $stmt = $this->db->prepare("DELETE FROM fixture_events WHERE fixture_id = ?");
        return $stmt->execute([$fixture_id]);
    }

    public function getByFixture($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_events WHERE fixture_id = ? ORDER BY time_elapsed ASC, time_extra ASC");
        $stmt->execute([$fixture_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

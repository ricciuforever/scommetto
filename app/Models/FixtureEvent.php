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
        $sql = "SELECT e.*, p.name as player_name, p.firstname, p.lastname 
                FROM fixture_events e
                LEFT JOIN players p ON e.player_id = p.id
                WHERE e.fixture_id = ? 
                ORDER BY e.time_elapsed ASC, e.time_extra ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fixture_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format for frontend
        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'team' => ['id' => $row['team_id']],
                'player' => ['id' => $row['player_id'], 'name' => $row['player_name'] ?? 'Unknown'],
                'assist' => ['id' => $row['assist_id']],
                'type' => $row['type'],
                'detail' => $row['detail'],
                'comments' => $row['comments'],
                'time' => [
                    'elapsed' => $row['time_elapsed'],
                    'extra' => $row['time_extra']
                ]
            ];
        }
        return $events;
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_events WHERE fixture_id = ?");
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

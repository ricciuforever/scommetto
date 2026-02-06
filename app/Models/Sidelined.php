<?php
// app/Models/Sidelined.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Sidelined
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function saveForPlayer($playerId, $data)
    {
        $this->deleteByPlayer($playerId);

        $sql = "INSERT INTO sidelined (player_id, type, start_date, end_date)
                VALUES (:pid, :type, :start, :end)";

        $stmt = $this->db->prepare($sql);
        foreach ($data as $s) {
            $stmt->execute([
                'pid' => $playerId,
                'type' => $s['type'] ?? null,
                'start' => $s['start'] ?? null,
                'end' => $s['end'] ?? null
            ]);
        }
    }

    public function saveForCoach($coachId, $data)
    {
        $this->deleteByCoach($coachId);

        $sql = "INSERT INTO sidelined (coach_id, type, start_date, end_date)
                VALUES (:cid, :type, :start, :end)";

        $stmt = $this->db->prepare($sql);
        foreach ($data as $s) {
            $stmt->execute([
                'cid' => $coachId,
                'type' => $s['type'] ?? null,
                'start' => $s['start'] ?? null,
                'end' => $s['end'] ?? null
            ]);
        }
    }

    public function deleteByPlayer($playerId)
    {
        $stmt = $this->db->prepare("DELETE FROM sidelined WHERE player_id = ?");
        return $stmt->execute([$playerId]);
    }

    public function deleteByCoach($coachId)
    {
        $stmt = $this->db->prepare("DELETE FROM sidelined WHERE coach_id = ?");
        return $stmt->execute([$coachId]);
    }

    public function getByPlayer($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM sidelined WHERE player_id = ? ORDER BY start_date DESC");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCoach($coachId)
    {
        $stmt = $this->db->prepare("SELECT * FROM sidelined WHERE coach_id = ? ORDER BY start_date DESC");
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($id, $type = 'player', $days = 30)
    {
        $column = ($type === 'coach') ? 'coach_id' : 'player_id';
        $stmt = $this->db->prepare("SELECT MAX(last_updated) FROM sidelined WHERE $column = ?");
        $stmt->execute([$id]);
        $last = $stmt->fetchColumn();

        if (!$last) return true;
        return (time() - strtotime($last)) > ($days * 86400);
    }
}

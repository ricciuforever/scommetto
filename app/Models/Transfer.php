<?php
// app/Models/Transfer.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Transfer
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function saveForPlayer($playerId, $transfers)
    {
        $this->deleteByPlayer($playerId);

        $sql = "INSERT INTO transfers (player_id, transfer_date, type, team_out_id, team_in_id)
                VALUES (:pid, :date, :type, :out, :in)";

        $stmt = $this->db->prepare($sql);
        foreach ($transfers as $t) {
            $stmt->execute([
                'pid' => $playerId,
                'date' => $t['date'] ?? null,
                'type' => $t['type'] ?? null,
                'out' => $t['teams']['out']['id'] ?? null,
                'in' => $t['teams']['in']['id'] ?? null
            ]);
        }
    }

    public function deleteByPlayer($playerId)
    {
        $stmt = $this->db->prepare("DELETE FROM transfers WHERE player_id = ?");
        return $stmt->execute([$playerId]);
    }

    public function getByPlayer($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM transfers WHERE player_id = ? ORDER BY transfer_date DESC");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($playerId, $days = 30)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) FROM transfers WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $last = $stmt->fetchColumn();

        if (!$last) return true;
        return (time() - strtotime($last)) > ($days * 86400);
    }
}

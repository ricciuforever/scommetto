<?php
// app/Models/H2H.php

namespace App\Models;

use App\Services\Database;
use PDO;

class H2H
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($team1_id, $team2_id, $h2hData)
    {
        // Ensure consistent order for primary key (smaller ID first)
        $t1 = min($team1_id, $team2_id);
        $t2 = max($team1_id, $team2_id);

        $sql = "INSERT INTO h2h_records (team1_id, team2_id, h2h_json) 
                VALUES (:t1, :t2, :h2h_json)
                ON DUPLICATE KEY UPDATE 
                h2h_json = VALUES(h2h_json), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            't1' => $t1,
            't2' => $t2,
            'h2h_json' => json_encode($h2hData)
        ]);
    }

    public function get($team1_id, $team2_id)
    {
        $t1 = min($team1_id, $team2_id);
        $t2 = max($team1_id, $team2_id);

        $stmt = $this->db->prepare("SELECT * FROM h2h_records WHERE team1_id = ? AND team2_id = ?");
        $stmt->execute([$t1, $t2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['h2h_json'] = json_decode($row['h2h_json'], true);
        }
        return $row;
    }

    public function needsRefresh($team1_id, $team2_id, $hours = 168)
    {
        $t1 = min($team1_id, $team2_id);
        $t2 = max($team1_id, $team2_id);

        $stmt = $this->db->prepare("SELECT last_updated FROM h2h_records WHERE team1_id = ? AND team2_id = ?");
        $stmt->execute([$t1, $t2]);
        $row = $stmt->fetch();
        if (!$row) return true;
        return (time() - strtotime($row['last_updated'])) > ($hours * 3600);
    }
}

<?php
// app/Models/LiveOdds.php

namespace App\Models;

use App\Services\Database;
use PDO;

class LiveOdds
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $data)
    {
        $sql = "INSERT INTO live_odds (fixture_id, odds_json, status_json)
                VALUES (:fid, :odds, :status)
                ON DUPLICATE KEY UPDATE
                    odds_json = VALUES(odds_json),
                    status_json = VALUES(status_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'odds' => json_encode($data['odds'] ?? []),
            'status' => json_encode($data['status'] ?? [])
        ]);
    }

    public function get($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM live_odds WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function hasOdds($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT 1 FROM live_odds WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        return $stmt->fetch() !== false;
    }
}

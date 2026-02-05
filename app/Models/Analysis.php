<?php
// app/Models/Analysis.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Analysis
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function wasRecentlyChecked($fixture_id, $minutes = 45)
    {
        $stmt = $this->db->prepare("SELECT last_checked FROM analyses WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        $row = $stmt->fetch();

        if (!$row)
            return false;

        $lastChecked = strtotime($row['last_checked']);
        return (time() - $lastChecked) < ($minutes * 60);
    }

    public function log($fixture_id, $prediction)
    {
        $sql = "INSERT INTO analyses (fixture_id, prediction_raw) 
                VALUES (:id, :pred) 
                ON DUPLICATE KEY UPDATE last_checked = CURRENT_TIMESTAMP, prediction_raw = VALUES(prediction_raw)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $fixture_id,
            'pred' => $prediction
        ]);
    }
}

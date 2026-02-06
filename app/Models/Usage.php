<?php
// app/Models/Usage.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Usage
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getLatest()
    {
        $stmt = $this->db->query("SELECT * FROM api_usage WHERE id = 1");
        return $stmt->fetch();
    }

    public function update($used, $remaining, $limit = null)
    {
        $sql = "UPDATE api_usage SET requests_used = :used, requests_remaining = :rem";
        $params = [
            'used' => $used,
            'rem' => $remaining
        ];

        if ($limit !== null) {
            $sql .= ", requests_limit = :limit";
            $params['limit'] = $limit;
        }

        $sql .= ", last_updated = CURRENT_TIMESTAMP WHERE id = 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}

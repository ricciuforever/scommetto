<?php
// app/Models/BetType.php

namespace App\Models;

use App\Services\Database;
use PDO;

class BetType
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($data)
    {
        $sql = "INSERT INTO bet_types (id, name) VALUES (:id, :name)
                ON DUPLICATE KEY UPDATE name = VALUES(name), last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name']
        ]);
    }

    public function getAll()
    {
        return $this->db->query("SELECT * FROM bet_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

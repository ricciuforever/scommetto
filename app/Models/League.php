<?php
// app/Models/League.php

namespace App\Models;

use App\Services\Database;
use PDO;

class League
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM leagues WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function save($data)
    {
        $sql = "INSERT INTO leagues (id, name, country, logo, season) 
                VALUES (:id, :name, :country, :logo, :season)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), country = VALUES(country), 
                logo = VALUES(logo), season = VALUES(season), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'country' => $data['country'] ?? null,
            'logo' => $data['logo'] ?? null,
            'season' => $data['season'] ?? date('Y')
        ]);
    }
}

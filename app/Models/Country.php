<?php
// app/Models/Country.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Country
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($data)
    {
        $sql = "INSERT INTO countries (name, code, flag) 
                VALUES (:name, :code, :flag) 
                ON DUPLICATE KEY UPDATE 
                    code = VALUES(code), 
                    flag = VALUES(flag),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'flag' => $data['flag'] ?? null
        ]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM countries ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($filters = [])
    {
        $sql = "SELECT * FROM countries WHERE 1=1";
        $params = [];

        if (!empty($filters['name'])) {
            $sql .= " AND name = :name";
            $params['name'] = $filters['name'];
        }
        if (!empty($filters['code'])) {
            $sql .= " AND code = :code";
            $params['code'] = $filters['code'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND name LIKE :search";
            $params['search'] = $filters['search'] . '%';
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($hours = 24)
    {
        $stmt = $this->db->query("SELECT MAX(last_updated) as last FROM countries");
        $row = $stmt->fetch();
        if (!$row || !$row['last'])
            return true;
        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

<?php
// app/Models/Season.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Season
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($year)
    {
        $sql = "INSERT INTO seasons (year) VALUES (:year) 
                ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['year' => $year]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT year FROM seasons ORDER BY year DESC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function needsRefresh($hours = 24)
    {
        $stmt = $this->db->query("SELECT MAX(last_updated) as last FROM seasons");
        $row = $stmt->fetch();
        if (!$row['last'])
            return true;
        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

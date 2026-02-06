<?php
// app/Models/PlayerSeason.php

namespace App\Models;

use App\Services\Database;
use PDO;

class PlayerSeason
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($year)
    {
        $sql = "INSERT INTO player_seasons (year) VALUES (:year)
                ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['year' => $year]);
    }

    public function getAll()
    {
        return $this->db->query("SELECT year FROM player_seasons ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
    }
}

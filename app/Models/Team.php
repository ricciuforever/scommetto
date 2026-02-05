<?php
// app/Models/Team.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Team
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function needsRefresh($id, $days = 7)
    {
        $team = $this->getById($id);
        if (!$team)
            return true;

        $lastUpdated = strtotime($team['last_updated']);
        return (time() - $lastUpdated) > ($days * 24 * 60 * 60);
    }

    public function save($data)
    {
        // Detect if we received the wrapper object with 'team' and 'venue'
        $team = isset($data['team']) ? $data['team'] : $data;
        $venue = isset($data['venue']) ? $data['venue'] : null;

        $sql = "INSERT INTO teams (id, name, logo, country, founded, venue_name, venue_capacity) 
                VALUES (:id, :name, :logo, :country, :founded, :venue_name, :venue_capacity)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), logo = VALUES(logo), country = VALUES(country), 
                founded = VALUES(founded), venue_name = VALUES(venue_name), 
                venue_capacity = VALUES(venue_capacity), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $team['id'],
            'name' => $team['name'],
            'logo' => $team['logo'] ?? null,
            'country' => $team['country'] ?? null,
            'founded' => $team['founded'] ?? null,
            'venue_name' => $venue['name'] ?? null,
            'venue_capacity' => $venue['capacity'] ?? null
        ]);
    }
}

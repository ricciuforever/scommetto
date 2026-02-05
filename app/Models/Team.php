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

    public function save($data)
    {
        $team = $data['team'];
        $venue = $data['venue'] ?? null;

        // Save Venue first if present
        if ($venue && isset($venue['id'])) {
            (new Venue())->save($venue);
        }

        $sql = "INSERT INTO teams (id, name, code, country, founded, national, logo, venue_id) 
                VALUES (:id, :name, :code, :country, :founded, :national, :logo, :venue_id)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), code = VALUES(code), country = VALUES(country),
                    founded = VALUES(founded), national = VALUES(national), 
                    logo = VALUES(logo), venue_id = VALUES(venue_id),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $team['id'],
            'name' => $team['name'],
            'code' => $team['code'] ?? null,
            'country' => $team['country'] ?? null,
            'founded' => $team['founded'] ?? null,
            'national' => ($team['national'] ?? false) ? 1 : 0,
            'logo' => $team['logo'] ?? null,
            'venue_id' => $venue['id'] ?? null
        ]);
    }

    public function linkToLeague($team_id, $league_id, $season)
    {
        $sql = "INSERT IGNORE INTO team_leagues (team_id, league_id, season) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$team_id, $league_id, $season]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

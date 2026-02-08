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

    public function needsRefresh($id, $hours = 24)
    {
        $stmt = $this->db->prepare("SELECT last_updated FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row)
            return true;

        $lastUpdated = strtotime($row['last_updated']);
        return (time() - $lastUpdated) > ($hours * 3600);
    }

    public function save($data)
    {
        $team = $data['team'] ?? $data;
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
        $sql = "INSERT INTO team_leagues (team_id, league_id, season) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$team_id, $league_id, $season]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Recupera le squadre per una specifica lega e stagione
     */
    public function getByLeagueAndSeason($leagueId, $season)
    {
        $sql = "SELECT t.*, v.name as venue_name, v.city as venue_city, v.image as venue_image
                FROM teams t
                JOIN team_leagues tl ON t.id = tl.team_id
                LEFT JOIN venues v ON t.venue_id = v.id
                WHERE tl.league_id = ? AND tl.season = ?
                ORDER BY t.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se le squadre di una lega/stagione necessitano di refresh
     */
    public function needsLeagueRefresh($leagueId, $season, $hours = 24)
    {
        $sql = "SELECT MAX(last_updated) as last FROM team_leagues WHERE league_id = ? AND season = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        $row = $stmt->fetch();

        if (!$row || !$row['last'])
            return true;

        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

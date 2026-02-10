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
                VALUES (:id, :name, :code, :country, :founded, :national, :logo, :venue_id)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(id) DO UPDATE SET
                    name = EXCLUDED.name, code = EXCLUDED.code, country = EXCLUDED.country,
                    founded = EXCLUDED.founded, national = EXCLUDED.national,
                    logo = EXCLUDED.logo, venue_id = EXCLUDED.venue_id,
                    last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    name = VALUES(name), code = VALUES(code), country = VALUES(country),
                    founded = VALUES(founded), national = VALUES(national), 
                    logo = VALUES(logo), venue_id = VALUES(venue_id),
                    last_updated = CURRENT_TIMESTAMP";
        }

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
        $sql = "INSERT INTO team_leagues (team_id, league_id, season) VALUES (?, ?, ?)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(team_id, league_id, season) DO UPDATE SET last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$team_id, $league_id, $season]);
    }

    /**
     * Aggiorna il timestamp di sincronizzazione per una lega/stagione
     * (Usato per segnare che il sync è avvenuto, anche se con 0 risultati)
     */
    public function touchLeagueSeason($leagueId, $season)
    {
        $sql = "INSERT INTO league_seasons (league_id, year, last_teams_sync)
                VALUES (?, ?, CURRENT_TIMESTAMP)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(league_id, year) DO UPDATE SET last_teams_sync = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE last_teams_sync = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$leagueId, $season]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function find($filters = [])
    {
        $sql = "SELECT DISTINCT t.id, t.name, t.code, t.logo, t.venue_id, t.country, t.founded, t.national,
                       v.name as venue_name, v.city as venue_city, v.image as venue_image
                FROM teams t
                LEFT JOIN venues v ON t.venue_id = v.id";

        $params = [];
        $where = ["1=1"];

        if (!empty($filters['league']) && !empty($filters['season'])) {
            $sql .= " JOIN team_leagues tl ON t.id = tl.team_id";
            $where[] = "tl.league_id = :league AND tl.season = :season";
            $params['league'] = $filters['league'];
            $params['season'] = $filters['season'];
        }

        if (!empty($filters['id'])) {
            $where[] = "t.id = :id";
            $params['id'] = $filters['id'];
        }
        if (!empty($filters['name'])) {
            $where[] = "t.name = :name";
            $params['name'] = $filters['name'];
        }
        if (!empty($filters['country'])) {
            $where[] = "t.country = :country";
            $params['country'] = $filters['country'];
        }
        if (!empty($filters['code'])) {
            $where[] = "t.code = :code";
            $params['code'] = $filters['code'];
        }
        if (!empty($filters['venue'])) {
            $where[] = "t.venue_id = :venue";
            $params['venue'] = $filters['venue'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(t.name LIKE :search OR t.country LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY t.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recupera le squadre per una specifica lega e stagione
     */
    public function getByLeagueAndSeason($leagueId, $season)
    {
        $sql = "SELECT t.id, t.name, t.code, t.logo, t.venue_id, t.country, t.founded, t.national,
                       v.name as venue_name, v.city as venue_city, v.image as venue_image
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
        // Controlliamo l'ultima sincronizzazione nella tabella league_seasons
        $sql = "SELECT last_teams_sync FROM league_seasons WHERE league_id = ? AND year = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_teams_sync'])
            return true;

        return (time() - strtotime($row['last_teams_sync'])) > ($hours * 3600);
    }

    /**
     * Salva le stagioni disponibili per una squadra
     */
    public function saveTeamSeasons($teamId, $seasons)
    {
        $sql = "INSERT INTO team_seasons (team_id, year) VALUES (?, ?)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(team_id, year) DO UPDATE SET last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        foreach ($seasons as $year) {
            $stmt->execute([$teamId, $year]);
        }
    }

    /**
     * Recupera le stagioni di una squadra
     */
    public function getTeamSeasons($teamId)
    {
        $stmt = $this->db->prepare("SELECT year FROM team_seasons WHERE team_id = ? ORDER BY year DESC");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Verifica se le stagioni di una squadra necessitano di refresh (24h)
     */
    public function needsTeamSeasonsRefresh($teamId, $hours = 24)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last FROM team_seasons WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last'])
            return true;

        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

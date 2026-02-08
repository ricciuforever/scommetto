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

    /**
     * Aggiorna il timestamp di sincronizzazione per una lega/stagione
     * (Usato anche quando non vengono trovate squadre per evitare loop di sync)
     */
    public function touchLeagueSeason($leagueId, $season)
    {
        // Se non ci sono record per tl, non possiamo fare ON DUPLICATE UPDATE se non abbiamo un team_id
        // Ma qui vogliamo solo segnare che abbiamo cercato.
        // In realtà needsLeagueRefresh usa MAX(last_updated).
        // Se la tabella tl è vuota per quella combinazione, non c'è nulla da aggiornare.
        // Potremmo inserire un record "dummy" o semplicemente accettare che riproverà.
        // In genere, se è una lega valida, avrà almeno una squadra.
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

    /**
     * Salva le stagioni disponibili per una squadra
     */
    public function saveTeamSeasons($teamId, $seasons)
    {
        $stmt = $this->db->prepare("INSERT INTO team_seasons (team_id, year) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP");
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

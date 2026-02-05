<?php
// app/Models/Standing.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Standing
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByLeague($leagueId)
    {
        $sql = "SELECT s.*, t.name as team_name, t.logo as team_logo 
                FROM standings s
                JOIN teams t ON s.team_id = t.id
                WHERE s.league_id = ?
                ORDER BY s.rank ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    public function getByTeamAndLeague($teamId, $leagueId)
    {
        $sql = "SELECT * FROM standings WHERE team_id = ? AND league_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teamId, $leagueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($leagueId, $hours = 6)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last FROM standings WHERE league_id = ?");
        $stmt->execute([$leagueId]);
        $row = $stmt->fetch();

        if (!$row['last'])
            return true;

        $lastUpdated = strtotime($row['last']);
        return (time() - $lastUpdated) > ($hours * 60 * 60);
    }

    public function save($leagueId, $teamData)
    {
        $sql = "INSERT INTO standings (league_id, team_id, rank, points, goals_diff, form) 
                VALUES (:league_id, :team_id, :rank, :points, :goals_diff, :form)
                ON DUPLICATE KEY UPDATE 
                rank = VALUES(rank), points = VALUES(points), 
                goals_diff = VALUES(goals_diff), form = VALUES(form), 
                last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'league_id' => $leagueId,
            'team_id' => $teamData['team']['id'],
            'rank' => $teamData['rank'],
            'points' => $teamData['points'],
            'goals_diff' => $teamData['goalsDiff'],
            'form' => $teamData['form'] ?? ''
        ]);
    }
}

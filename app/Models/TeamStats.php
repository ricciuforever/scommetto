<?php
// app/Models/TeamStats.php

namespace App\Models;

use App\Services\Database;
use PDO;

class TeamStats
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($team_id, $league_id, $season, $data)
    {
        $sql = "INSERT INTO team_stats (team_id, league_id, season, played, wins, draws, losses, goals_for, goals_against, clean_sheets, failed_to_score, avg_goals_for, avg_goals_against) 
                VALUES (:tid, :lid, :season, :played, :wins, :draws, :losses, :gf, :ga, :cs, :fts, :avgfc, :avgac) 
                ON DUPLICATE KEY UPDATE 
                    played = VALUES(played), wins = VALUES(wins), draws = VALUES(draws), losses = VALUES(losses),
                    goals_for = VALUES(goals_for), goals_against = VALUES(goals_against),
                    clean_sheets = VALUES(clean_sheets), failed_to_score = VALUES(failed_to_score),
                    avg_goals_for = VALUES(avg_goals_for), avg_goals_against = VALUES(avg_goals_against),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'tid' => $team_id,
            'lid' => $league_id,
            'season' => $season,
            'played' => $data['fixtures']['played']['total'] ?? 0,
            'wins' => $data['fixtures']['wins']['total'] ?? 0,
            'draws' => $data['fixtures']['draws']['total'] ?? 0,
            'losses' => $data['fixtures']['loses']['total'] ?? 0,
            'gf' => $data['goals']['for']['total']['total'] ?? 0,
            'ga' => $data['goals']['against']['total']['total'] ?? 0,
            'cs' => $data['clean_sheet']['total'] ?? 0,
            'fts' => $data['failed_to_score']['total'] ?? 0,
            'avgfc' => $data['goals']['for']['average']['total'] ?? 0,
            'avgac' => $data['goals']['against']['average']['total'] ?? 0
        ]);
    }

    public function get($team_id, $league_id, $season)
    {
        $stmt = $this->db->prepare("SELECT * FROM team_stats WHERE team_id = ? AND league_id = ? AND season = ?");
        $stmt->execute([$team_id, $league_id, $season]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

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

    public function save($team_id, $league_id, $season, $data, $date = '0000-00-00')
    {
        $sql = "INSERT INTO team_stats (team_id, league_id, season, date, played, wins, draws, losses, goals_for, goals_against, clean_sheets, failed_to_score, avg_goals_for, avg_goals_against, full_stats_json)
                VALUES (:tid, :lid, :season, :date, :played, :wins, :draws, :losses, :gf, :ga, :cs, :fts, :avgfc, :avgac, :full)
                ON DUPLICATE KEY UPDATE 
                    played = VALUES(played), wins = VALUES(wins), draws = VALUES(draws), losses = VALUES(losses),
                    goals_for = VALUES(goals_for), goals_against = VALUES(goals_against),
                    clean_sheets = VALUES(clean_sheets), failed_to_score = VALUES(failed_to_score),
                    avg_goals_for = VALUES(avg_goals_for), avg_goals_against = VALUES(avg_goals_against),
                    full_stats_json = VALUES(full_stats_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'tid' => $team_id,
            'lid' => $league_id,
            'season' => $season,
            'date' => $date ?: '0000-00-00',
            'played' => $data['fixtures']['played']['total'] ?? 0,
            'wins' => $data['fixtures']['wins']['total'] ?? 0,
            'draws' => $data['fixtures']['draws']['total'] ?? 0,
            'losses' => $data['fixtures']['loses']['total'] ?? 0,
            'gf' => $data['goals']['for']['total']['total'] ?? 0,
            'ga' => $data['goals']['against']['total']['total'] ?? 0,
            'cs' => $data['clean_sheet']['total'] ?? 0,
            'fts' => $data['failed_to_score']['total'] ?? 0,
            'avgfc' => $data['goals']['for']['average']['total'] ?? 0,
            'avgac' => $data['goals']['against']['average']['total'] ?? 0,
            'full' => json_encode($data)
        ]);
    }

    public function get($team_id, $league_id, $season, $date = '0000-00-00')
    {
        $sql = "SELECT * FROM team_stats WHERE team_id = ? AND league_id = ? AND season = ? AND date = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$team_id, $league_id, $season, $date ?: '0000-00-00']);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($team_id, $league_id, $season, $hours = 24, $date = '0000-00-00')
    {
        $sql = "SELECT last_updated FROM team_stats WHERE team_id = ? AND league_id = ? AND season = ? AND date = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$team_id, $league_id, $season, $date ?: '0000-00-00']);
        $row = $stmt->fetch();

        if (!$row)
            return true;

        return (time() - strtotime($row['last_updated'])) > ($hours * 3600);
    }
}

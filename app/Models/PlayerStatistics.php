<?php
// app/Models/PlayerStatistics.php

namespace App\Models;

use App\Services\Database;
use PDO;

class PlayerStatistics
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($playerId, $teamId, $leagueId, $season, $stats)
    {
        $sql = "INSERT INTO player_statistics (player_id, team_id, league_id, season, stats_json)
                VALUES (:pid, :tid, :lid, :season, :stats)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(player_id, team_id, league_id, season) DO UPDATE SET
                    stats_json = EXCLUDED.stats_json, last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    stats_json = VALUES(stats_json), last_updated = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'pid' => $playerId,
            'tid' => $teamId,
            'lid' => $leagueId,
            'season' => $season,
            'stats' => json_encode($stats)
        ]);
    }

    public function getByPlayer($playerId, $season = null)
    {
        $sql = "SELECT * FROM player_statistics WHERE player_id = ?";
        $params = [$playerId];
        if ($season) {
            $sql .= " AND season = ?";
            $params[] = $season;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByTeam($teamId, $season)
    {
        $stmt = $this->db->prepare("SELECT * FROM player_statistics WHERE team_id = ? AND season = ?");
        $stmt->execute([$teamId, $season]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get($playerId, $season)
    {
        $stmt = $this->db->prepare("SELECT * FROM player_statistics WHERE player_id = ? AND season = ?");
        $stmt->execute([$playerId, $season]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($teamId, $season, $days = 7)
    {
        // We check refresh based on team since we fetch them by team
        $stmt = $this->db->prepare("SELECT MAX(last_updated) FROM player_statistics WHERE team_id = ? AND season = ?");
        $stmt->execute([$teamId, $season]);
        $last = $stmt->fetchColumn();

        if (!$last)
            return true;
        return (time() - strtotime($last)) > ($days * 86400);
    }
}

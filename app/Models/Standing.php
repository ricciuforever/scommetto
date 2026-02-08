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

    /**
     * Salva un singolo elemento della classifica
     */
    public function save($leagueId, $season, $item)
    {
        $sql = "INSERT INTO standings (
                    league_id, team_id, season, rank, points, goals_diff, form,
                    group_name, description, played, win, draw, lose,
                    goals_for, goals_against, home_stats_json, away_stats_json
                ) VALUES (
                    :lid, :tid, :season, :rank, :points, :gd, :form,
                    :group, :desc, :played, :win, :draw, :lose,
                    :gf, :ga, :home_json, :away_json
                ) ON DUPLICATE KEY UPDATE
                    rank = VALUES(rank),
                    points = VALUES(points),
                    goals_diff = VALUES(goals_diff),
                    form = VALUES(form),
                    group_name = VALUES(group_name),
                    description = VALUES(description),
                    played = VALUES(played),
                    win = VALUES(win),
                    draw = VALUES(draw),
                    lose = VALUES(lose),
                    goals_for = VALUES(goals_for),
                    goals_against = VALUES(goals_against),
                    home_stats_json = VALUES(home_stats_json),
                    away_stats_json = VALUES(away_stats_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'lid'       => $leagueId,
            'tid'       => $item['team']['id'],
            'season'    => $season,
            'rank'      => $item['rank'],
            'points'    => $item['points'],
            'gd'        => $item['goalsDiff'],
            'form'      => $item['form'] ?? null,
            'group'     => $item['group'] ?? null,
            'desc'      => $item['description'] ?? null,
            'played'    => $item['all']['played'] ?? 0,
            'win'       => $item['all']['win'] ?? 0,
            'draw'      => $item['all']['draw'] ?? 0,
            'lose'      => $item['all']['lose'] ?? 0,
            'gf'        => $item['all']['goals']['for'] ?? 0,
            'ga'        => $item['all']['goals']['against'] ?? 0,
            'home_json' => json_encode($item['home'] ?? []),
            'away_json' => json_encode($item['away'] ?? [])
        ]);
    }

    /**
     * Recupera la classifica per una lega e stagione
     */
    public function getByLeagueAndSeason($leagueId, $season)
    {
        $sql = "SELECT s.*, t.name as team_name, t.logo as team_logo
                FROM standings s
                JOIN teams t ON s.team_id = t.id
                WHERE s.league_id = ? AND s.season = ?
                ORDER BY s.group_name ASC, s.rank ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($filters = [])
    {
        $sql = "SELECT s.*, t.name as team_name, t.logo as team_logo, l.name as league_name
                FROM standings s
                JOIN teams t ON s.team_id = t.id
                JOIN leagues l ON s.league_id = l.id
                WHERE 1=1";

        $params = [];
        if (!empty($filters['league'])) {
            $sql .= " AND s.league_id = :league";
            $params['league'] = $filters['league'];
        }
        if (!empty($filters['team'])) {
            $sql .= " AND s.team_id = :team";
            $params['team'] = $filters['team'];
        }
        if (!empty($filters['season'])) {
            $sql .= " AND s.season = :season";
            $params['season'] = $filters['season'];
        }

        $sql .= " ORDER BY s.group_name ASC, s.rank ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se i dati necessitano di refresh
     */
    public function needsRefresh($leagueId, $season, $hours = 1)
    {
        // Controlliamo l'ultima sincronizzazione nella tabella league_seasons
        $sql = "SELECT last_standings_sync FROM league_seasons WHERE league_id = ? AND year = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_standings_sync'])
            return true;

        return (time() - strtotime($row['last_standings_sync'])) > ($hours * 3600);
    }
}

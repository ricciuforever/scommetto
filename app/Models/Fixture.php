<?php
// app/Models/Fixture.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Fixture
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($data)
    {
        $sql = "INSERT INTO fixtures (id, league_id, round, team_home_id, team_away_id, date, status_short, status_long, elapsed, score_home, score_away, venue_id) 
                VALUES (:id, :league_id, :round, :home_id, :away_id, :date, :status_short, :status_long, :elapsed, :score_home, :score_away, :venue_id) 
                ON DUPLICATE KEY UPDATE 
                    status_short = VALUES(status_short), 
                    status_long = VALUES(status_long),
                    elapsed = VALUES(elapsed),
                    score_home = VALUES(score_home), 
                    score_away = VALUES(score_away),
                    round = VALUES(round),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['fixture']['id'],
            'league_id' => $data['league']['id'],
            'round' => $data['league']['round'] ?? null,
            'home_id' => $data['teams']['home']['id'],
            'away_id' => $data['teams']['away']['id'],
            'date' => date('Y-m-d H:i:s', strtotime($data['fixture']['date'])),
            'status_short' => $data['fixture']['status']['short'],
            'status_long' => $data['fixture']['status']['long'],
            'elapsed' => $data['fixture']['status']['elapsed'] ?? null,
            'score_home' => $data['goals']['home'],
            'score_away' => $data['goals']['away'],
            'venue_id' => $data['fixture']['venue']['id'] ?? null
        ]);
    }

    public function getTeamRecent($team_id, $limit = 5)
    {
        $sql = "SELECT f.*, 
                th.name as home_name, ta.name as away_name
                FROM fixtures f
                JOIN teams th ON f.team_home_id = th.id
                JOIN teams ta ON f.team_away_id = ta.id
                WHERE (f.team_home_id = ? OR f.team_away_id = ?)
                AND f.status_short IN ('FT', 'AET', 'PEN') 
                ORDER BY f.date DESC LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $team_id, PDO::PARAM_INT);
        $stmt->bindValue(3, (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

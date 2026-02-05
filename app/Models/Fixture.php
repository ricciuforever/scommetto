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
        $sql = "INSERT INTO fixtures (id, league_id, team_home_id, team_away_id, date, status, score_home, score_away) 
                VALUES (:id, :league_id, :home_id, :away_id, :date, :status, :score_home, :score_away) 
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    score_home = VALUES(score_home), 
                    score_away = VALUES(score_away),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['fixture']['id'],
            'league_id' => $data['league']['id'],
            'home_id' => $data['teams']['home']['id'],
            'away_id' => $data['teams']['away']['id'],
            'date' => date('Y-m-d H:i:s', strtotime($data['fixture']['date'])),
            'status' => $data['fixture']['status']['short'],
            'score_home' => $data['goals']['home'],
            'score_away' => $data['goals']['away']
        ]);
    }

    public function getTeamRecent($team_id, $limit = 5)
    {
        $sql = "SELECT * FROM fixtures 
                WHERE (team_home_id = :tid OR team_away_id = :tid) 
                AND status IN ('FT', 'AET', 'PEN') 
                ORDER BY date DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tid', $team_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

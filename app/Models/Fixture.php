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

    public function updateDetailedTimestamp($id)
    {
        $stmt = $this->db->prepare("UPDATE fixtures SET last_detailed_update = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixtures WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTeamRecent($team_id, $limit = 5)
    {
        $limit = (int) $limit;
        $sql = "SELECT f.*, 
                th.name as home_name, ta.name as away_name
                FROM fixtures f
                JOIN teams th ON f.team_home_id = th.id
                JOIN teams ta ON f.team_away_id = ta.id
                WHERE (f.team_home_id = ? OR f.team_away_id = ?)
                AND f.status_short IN ('FT', 'AET', 'PEN') 
                ORDER BY f.date DESC LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$team_id, $team_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Controlla se ci sono match in corso o che inizieranno a breve
     */
    public function hasActiveOrUpcoming($bufferHours = 2)
    {
        // Match iniziati nelle ultime 3 ore (potrebbero essere ancora live)
        // o che inizieranno nelle prossime $bufferHours ore
        $sql = "SELECT COUNT(*) as count FROM fixtures
                WHERE (date BETWEEN DATE_SUB(NOW(), INTERVAL 3 HOUR) AND DATE_ADD(NOW(), INTERVAL ? HOUR))
                OR status_short NOT IN ('FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bufferHours]);
        $row = $stmt->fetch();
        return ($row['count'] > 0);
    }

    public function getActiveFixtures()
    {
        $sql = "SELECT * FROM fixtures WHERE status_short NOT IN ('FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD')";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

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
        $sql = "INSERT INTO fixtures (id, league_id, round, team_home_id, team_away_id, date, status_short, status_long, elapsed, score_home, score_away, score_home_ht, score_away_ht, venue_id)
                VALUES (:id, :league_id, :round, :home_id, :away_id, :date, :status_short, :status_long, :elapsed, :score_home, :score_away, :score_home_ht, :score_away_ht, :venue_id)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(id) DO UPDATE SET
                    status_short = EXCLUDED.status_short,
                    status_long = EXCLUDED.status_long,
                    elapsed = EXCLUDED.elapsed,
                    score_home = EXCLUDED.score_home,
                    score_away = EXCLUDED.score_away,
                    score_home_ht = EXCLUDED.score_home_ht,
                    score_away_ht = EXCLUDED.score_away_ht,
                    round = EXCLUDED.round,
                    last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    status_short = VALUES(status_short), 
                    status_long = VALUES(status_long),
                    elapsed = VALUES(elapsed),
                    score_home = VALUES(score_home), 
                    score_away = VALUES(score_away),
                    score_home_ht = VALUES(score_home_ht),
                    score_away_ht = VALUES(score_away_ht),
                    round = VALUES(round),
                    last_updated = CURRENT_TIMESTAMP";
        }

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
            'score_home_ht' => $data['score']['halftime']['home'] ?? null,
            'score_away_ht' => $data['score']['halftime']['away'] ?? null,
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
        $sql = "SELECT f.*,
                       t1.name as team_home_name, t1.logo as team_home_logo,
                       t2.name as team_away_name, t2.logo as team_away_logo,
                       l.name as league_name, l.logo as league_logo,
                       v.name as venue_name, v.city as venue_city
                FROM fixtures f
                JOIN teams t1 ON f.team_home_id = t1.id
                JOIN teams t2 ON f.team_away_id = t2.id
                JOIN leagues l ON f.league_id = l.id
                LEFT JOIN venues v ON f.venue_id = v.id
                WHERE f.id = ?";
        $stmt = $this->db->prepare($sql);
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

    /**
     * Verifica se le partite di una lega/stagione necessitano di refresh
     */
    public function needsLeagueRefresh($leagueId, $season, $hours = 24)
    {
        $sql = "SELECT last_fixtures_sync FROM league_seasons WHERE league_id = ? AND year = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$leagueId, $season]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_fixtures_sync'])
            return true;
        return (time() - strtotime($row['last_fixtures_sync'])) > ($hours * 3600);
    }

    /**
     * Aggiorna il timestamp di sincronizzazione per una lega/stagione
     */
    public function touchLeagueSeason($leagueId, $season)
    {
        $sql = "INSERT INTO league_seasons (league_id, year, last_fixtures_sync)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE last_fixtures_sync = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$leagueId, $season]);
    }
}

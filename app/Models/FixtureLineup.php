<?php
// app/Models/FixtureLineup.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixtureLineup
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $team_id, $data)
    {
        $sql = "INSERT INTO fixture_lineups (fixture_id, team_id, formation, coach_id, start_xi_json, substitutes_json)
                VALUES (:fid, :tid, :formation, :coach_id, :start_xi, :subs)
                ON DUPLICATE KEY UPDATE
                    formation = VALUES(formation),
                    coach_id = VALUES(coach_id),
                    start_xi_json = VALUES(start_xi_json),
                    substitutes_json = VALUES(substitutes_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'tid' => $team_id,
            'formation' => $data['formation'],
            'coach_id' => $data['coach']['id'] ?? null,
            'start_xi' => json_encode($data['startXI']),
            'subs' => json_encode($data['substitutes'])
        ]);
    }

    public function get($fixture_id, $team_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_lineups WHERE fixture_id = ? AND team_id = ?");
        $stmt->execute([$fixture_id, $team_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

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
                VALUES (:fid, :tid, :formation, :coach_id, :start_xi, :subs)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(fixture_id, team_id) DO UPDATE SET
                    formation = EXCLUDED.formation,
                    coach_id = EXCLUDED.coach_id,
                    start_xi_json = EXCLUDED.start_xi_json,
                    substitutes_json = EXCLUDED.substitutes_json,
                    last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    formation = VALUES(formation),
                    coach_id = VALUES(coach_id),
                    start_xi_json = VALUES(start_xi_json),
                    substitutes_json = VALUES(substitutes_json),
                    last_updated = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'tid' => $team_id,
            'formation' => $data['formation'] ?? null,
            'coach_id' => $data['coach']['id'] ?? null,
            'start_xi' => json_encode($data['startXI'] ?? []),
            'subs' => json_encode($data['substitutes'] ?? [])
        ]);
    }

    public function get($fixture_id, $team_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_lineups WHERE fixture_id = ? AND team_id = ?");
        $stmt->execute([$fixture_id, $team_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByFixture($fixture_id)
    {
        $sql = "SELECT fl.*, t.name as team_name, t.logo as team_logo
                FROM fixture_lineups fl
                JOIN teams t ON fl.team_id = t.id
                WHERE fl.fixture_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fixture_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['start_xi_json'] = json_decode($row['start_xi_json'], true);
            $row['substitutes_json'] = json_decode($row['substitutes_json'], true);
        }

        return $results;
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_lineups WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_sync'])
            return true;

        $lastSync = strtotime($row['last_sync']);

        // Lineups are updated every 15 minutes
        $isLive = in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'P', 'BT']);

        if ($isLive) {
            return (time() - $lastSync) > 900; // 15 minuti se live
        }

        // Se non è ancora iniziato, controlliamo se mancano meno di 45 minuti all'inizio
        $fixtureModel = new Fixture();
        $fixture = $fixtureModel->getById($fixtureId);
        if ($fixture && $statusShort === 'NS') {
            $startTime = strtotime($fixture['date']);
            if ($startTime - time() < 2700) { // < 45 minuti
                return (time() - $lastSync) > 900; // rinfresca ogni 15 minuti
            }
        }

        return (time() - $lastSync) > 86400; // 24 ore altrimenti
    }
}

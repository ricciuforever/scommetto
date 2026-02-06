<?php
// app/Models/Trophy.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Trophy
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function saveForPlayer($playerId, $data)
    {
        // First, clear old trophies to avoid duplicates if API doesn't provide unique IDs for trophies
        // Alternatively, we can use a more sophisticated check.
        // But since trophies is a simple list, clearing and re-inserting is safer for consistency.
        $this->deleteByPlayer($playerId);

        $sql = "INSERT INTO trophies (player_id, league, country, season, place)
                VALUES (:pid, :league, :country, :season, :place)";

        $stmt = $this->db->prepare($sql);
        foreach ($data as $t) {
            $stmt->execute([
                'pid' => $playerId,
                'league' => $t['league'] ?? null,
                'country' => $t['country'] ?? null,
                'season' => $t['season'] ?? null,
                'place' => $t['place'] ?? null
            ]);
        }
    }

    public function saveForCoach($coachId, $data)
    {
        $this->deleteByCoach($coachId);

        $sql = "INSERT INTO trophies (coach_id, league, country, season, place)
                VALUES (:cid, :league, :country, :season, :place)";

        $stmt = $this->db->prepare($sql);
        foreach ($data as $t) {
            $stmt->execute([
                'cid' => $coachId,
                'league' => $t['league'] ?? null,
                'country' => $t['country'] ?? null,
                'season' => $t['season'] ?? null,
                'place' => $t['place'] ?? null
            ]);
        }
    }

    public function deleteByPlayer($playerId)
    {
        $stmt = $this->db->prepare("DELETE FROM trophies WHERE player_id = ?");
        return $stmt->execute([$playerId]);
    }

    public function deleteByCoach($coachId)
    {
        $stmt = $this->db->prepare("DELETE FROM trophies WHERE coach_id = ?");
        return $stmt->execute([$coachId]);
    }

    public function getByPlayer($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM trophies WHERE player_id = ? ORDER BY season DESC");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCoach($coachId)
    {
        $stmt = $this->db->prepare("SELECT * FROM trophies WHERE coach_id = ? ORDER BY season DESC");
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($id, $type = 'player', $days = 30)
    {
        $column = ($type === 'coach') ? 'coach_id' : 'player_id';
        $stmt = $this->db->prepare("SELECT MAX(last_updated) FROM trophies WHERE $column = ?");
        $stmt->execute([$id]);
        $last = $stmt->fetchColumn();

        if (!$last) return true;
        return (time() - strtotime($last)) > ($days * 86400);
    }
}

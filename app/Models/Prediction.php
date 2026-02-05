<?php
// app/Models/Prediction.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Prediction
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByFixtureId($fixtureId)
    {
        $stmt = $this->db->prepare("SELECT * FROM predictions WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        return $stmt->fetch();
    }

    public function needsRefresh($fixtureId)
    {
        $prediction = $this->getByFixtureId($fixtureId);
        if (!$prediction) return true;

        $lastUpdated = strtotime($prediction['updated_at']);
        // Refresh every hour
        return (time() - $lastUpdated) > 3600;
    }

    public function save($fixtureId, $data)
    {
        $advice = $data['predictions']['advice'] ?? null;
        $winnerId = $data['predictions']['winner']['id'] ?? null;
        $winnerName = $data['predictions']['winner']['name'] ?? null;
        $winOrDraw = isset($data['predictions']['win_or_draw']) ? ($data['predictions']['win_or_draw'] ? 1 : 0) : null;

        $comparison = json_encode($data['comparison'] ?? []);
        $percent = json_encode($data['predictions']['percent'] ?? []);

        $sql = "INSERT INTO predictions (fixture_id, advice, winner_id, winner_name, win_or_draw, comparison, percent, updated_at)
                VALUES (:fixture_id, :advice, :winner_id, :winner_name, :win_or_draw, :comparison, :percent, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                advice = VALUES(advice), winner_id = VALUES(winner_id),
                winner_name = VALUES(winner_name), win_or_draw = VALUES(win_or_draw),
                comparison = VALUES(comparison), percent = VALUES(percent),
                updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fixture_id' => $fixtureId,
            'advice' => $advice,
            'winner_id' => $winnerId,
            'winner_name' => $winnerName,
            'win_or_draw' => $winOrDraw,
            'comparison' => $comparison,
            'percent' => $percent
        ]);
    }
}

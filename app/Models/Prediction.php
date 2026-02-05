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

    public function getByFixtureId($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM predictions WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row)
            return null;

        return [
            'advice' => $row['advice'],
            'comparison' => json_decode($row['comparison_json'], true),
            'percent' => json_decode($row['percent_json'], true)
        ];
    }

    public function save($fixture_id, $data)
    {
        $sql = "INSERT INTO predictions (fixture_id, advice, comparison_json, percent_json) 
                VALUES (:id, :advice, :comp, :perc) 
                ON DUPLICATE KEY UPDATE 
                    advice = VALUES(advice), 
                    comparison_json = VALUES(comparison_json), 
                    percent_json = VALUES(percent_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $fixture_id,
            'advice' => $data['predictions']['advice'] ?? 'N/A',
            'comp' => json_encode($data['comparison'] ?? []),
            'perc' => json_encode($data['predictions']['percent'] ?? [])
        ]);
    }

    public function needsRefresh($fixture_id, $hours = 24)
    {
        $stmt = $this->db->prepare("SELECT last_updated FROM predictions WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        $row = $stmt->fetch();

        if (!$row)
            return true;

        $lastUpdated = strtotime($row['last_updated']);
        return (time() - $lastUpdated) > ($hours * 3600);
    }
}

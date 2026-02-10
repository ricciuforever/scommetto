<?php
// app/Models/FixturePrediction.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixturePrediction
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTableExists();
    }

    private function ensureTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS fixture_predictions (
            fixture_id INT PRIMARY KEY,
            prediction_json JSON,
            comparison_json JSON,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->exec($sql);
    }

    public function save($fixtureId, $predictionData, $comparisonData)
    {
        $sql = "INSERT INTO fixture_predictions (fixture_id, prediction_json, comparison_json, last_updated)
                VALUES (:fid, :pred, :comp, CURRENT_TIMESTAMP)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(fixture_id) DO UPDATE SET
                    prediction_json = EXCLUDED.prediction_json,
                    comparison_json = EXCLUDED.comparison_json,
                    last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    prediction_json = VALUES(prediction_json),
                    comparison_json = VALUES(comparison_json),
                    last_updated = CURRENT_TIMESTAMP";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixtureId,
            'pred' => json_encode($predictionData),
            'comp' => json_encode($comparisonData)
        ]);
    }

    public function getByFixture($fixtureId)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_predictions WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['prediction_json'] = json_decode($row['prediction_json'], true);
            $row['comparison_json'] = json_decode($row['comparison_json'], true);
        }

        return $row;
    }

    public function needsRefresh($fixtureId, $statusShort)
    {
        $stmt = $this->db->prepare("SELECT MAX(last_updated) as last_sync FROM fixture_predictions WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        $row = $stmt->fetch();

        if (!$row || !$row['last_sync'])
            return true;

        $lastSync = strtotime($row['last_sync']);

        // Predictions are updated every hour by the API
        $isFinished = in_array($statusShort, ['FT', 'AET', 'PEN']);

        if ($isFinished) {
            return (time() - $lastSync) > 86400; // 24 ore per i terminati (non dovrebbero cambiare, ma per sicurezza)
        }

        return (time() - $lastSync) > 3600; // 1 ora altrimenti
    }
}

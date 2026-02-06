<?php
// app/Models/FixtureOdds.php

namespace App\Models;

use App\Services\Database;
use PDO;

class FixtureOdds
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($fixture_id, $bookmaker_id, $bet_id, $odds_json)
    {
        $sql = "INSERT INTO fixture_odds (fixture_id, bookmaker_id, bet_id, odds_json)
                VALUES (:fid, :bid, :bet_id, :odds)
                ON DUPLICATE KEY UPDATE
                    odds_json = VALUES(odds_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'fid' => $fixture_id,
            'bid' => $bookmaker_id,
            'bet_id' => $bet_id,
            'odds' => json_encode($odds_json)
        ]);
    }

    public function getByFixture($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM fixture_odds WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php
// app/Models/Bet.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Bet
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM bets ORDER BY timestamp DESC");
        return $stmt->fetchAll();
    }

    public function create($data): string
    {
        $sql = "INSERT INTO bets (fixture_id, bookmaker_id, bookmaker_name, match_name, advice, market, odds, stake, urgency, confidence, status, timestamp) 
                VALUES (:fixture_id, :bookmaker_id, :bookmaker_name, :match_name, :advice, :market, :odds, :stake, :urgency, :confidence, :status, :timestamp)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fixture_id' => (int) $data['fixture_id'],
            'bookmaker_id' => isset($data['bookmaker_id']) ? (int) $data['bookmaker_id'] : null,
            'bookmaker_name' => $data['bookmaker_name'] ?? null,
            'match_name' => $data['match'] ?? $data['match_name'],
            'advice' => $data['advice'] ?? '',
            'market' => $data['market'] ?? '',
            'odds' => $data['odds'] ?? 0,
            'stake' => $data['stake'] ?? 0,
            'urgency' => $data['urgency'] ?? 'Medium',
            'confidence' => (int) ($data['confidence'] ?? 0),
            'status' => $data['status'] ?? 'pending',
            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->db->lastInsertId();
    }

    public function isPending($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM bets WHERE fixture_id = ? AND status = 'pending'");
        $stmt->execute([$fixture_id]);
        return $stmt->fetch() !== false;
    }

    public function hasBet($fixture_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM bets WHERE fixture_id = ?");
        $stmt->execute([$fixture_id]);
        return $stmt->fetch() !== false;
    }

    public function updateStatus($id, $status, $result = null)
    {
        $sql = "UPDATE bets SET status = :status, result = :result WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'result' => $result,
            'id' => $id
        ]);
    }
    public function cleanup()
    {
        $sql = "DELETE FROM bets WHERE stake <= 0";
        return $this->db->query($sql);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM bets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deduplicate()
    {
        // Rimuove i duplicati 'pending' per lo stesso match, tenendo solo quello con ID più alto (più recente)
        $sql = "DELETE b1 FROM bets b1
                INNER JOIN bets b2 
                WHERE b1.id < b2.id 
                AND b1.fixture_id = b2.fixture_id 
                AND b1.status = 'pending' 
                AND b2.status = 'pending'";
        return $this->db->query($sql);
    }
}

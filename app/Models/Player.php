<?php
// app/Models/Player.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Player
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByTeam($teamId)
    {
        $sql = "SELECT p.*, s.position, s.number 
                FROM players p
                JOIN squads s ON p.id = s.player_id
                WHERE s.team_id = ?
                ORDER BY s.number ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function save($playerData)
    {
        $sql = "INSERT INTO players (id, name, firstname, lastname, age, nationality, height, weight, photo) 
                VALUES (:id, :name, :firstname, :lastname, :age, :nationality, :height, :weight, :photo)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), age = VALUES(age), photo = VALUES(photo), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $playerData['id'],
            'name' => $playerData['name'],
            'firstname' => $playerData['firstname'] ?? '',
            'lastname' => $playerData['lastname'] ?? '',
            'age' => $playerData['age'] ?? null,
            'nationality' => $playerData['nationality'] ?? '',
            'height' => $playerData['height'] ?? null,
            'weight' => $playerData['weight'] ?? null,
            'photo' => $playerData['photo'] ?? null
        ]);
    }

    public function linkToSquad($teamId, $playerData, $squadInfo)
    {
        $sql = "INSERT INTO squads (team_id, player_id, position, number) 
                VALUES (:team_id, :player_id, :position, :number)
                ON DUPLICATE KEY UPDATE 
                position = VALUES(position), number = VALUES(number), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'team_id' => $teamId,
            'player_id' => $playerData['id'],
            'position' => $squadInfo['position'] ?? '',
            'number' => $squadInfo['number'] ?? null
        ]);
    }
}

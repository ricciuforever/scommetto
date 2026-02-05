<?php
// app/Models/Coach.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Coach
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByTeam($teamId)
    {
        $stmt = $this->db->prepare("SELECT * FROM coaches WHERE team_id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetch();
    }

    public function save($data, $teamId)
    {
        $sql = "INSERT INTO coaches (id, name, firstname, lastname, age, nationality, photo, team_id) 
                VALUES (:id, :name, :firstname, :lastname, :age, :nationality, :photo, :team_id)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), age = VALUES(age), photo = VALUES(photo), 
                team_id = VALUES(team_id), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'firstname' => $data['firstname'] ?? '',
            'lastname' => $data['lastname'] ?? '',
            'age' => $data['age'] ?? null,
            'nationality' => $data['nationality'] ?? '',
            'photo' => $data['photo'] ?? null,
            'team_id' => $teamId
        ]);
    }
}

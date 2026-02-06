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

    public function needsRefresh($teamId, $hours = 24)
    {
        $stmt = $this->db->prepare("SELECT last_updated FROM coaches WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $row = $stmt->fetch();

        if (!$row)
            return true;

        $lastUpdated = strtotime($row['last_updated']);
        return (time() - $lastUpdated) > ($hours * 3600);
    }

    public function getByTeam($teamId)
    {
        $stmt = $this->db->prepare("SELECT * FROM coaches WHERE team_id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetch();
    }

    public function save($data, $teamId)
    {
        $sql = "INSERT INTO coaches (id, name, firstname, lastname, age, birth_date, birth_country, nationality, photo, team_id, career_json)
                VALUES (:id, :name, :firstname, :lastname, :age, :birth_date, :birth_country, :nationality, :photo, :team_id, :career)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                age = VALUES(age),
                photo = VALUES(photo),
                team_id = VALUES(team_id),
                birth_date = VALUES(birth_date),
                birth_country = VALUES(birth_country),
                career_json = VALUES(career_json),
                last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'firstname' => $data['firstname'] ?? '',
            'lastname' => $data['lastname'] ?? '',
            'age' => $data['age'] ?? null,
            'birth_date' => $data['birth']['date'] ?? null,
            'birth_country' => $data['birth']['country'] ?? null,
            'nationality' => $data['nationality'] ?? '',
            'photo' => $data['photo'] ?? null,
            'team_id' => $teamId,
            'career' => isset($data['career']) ? json_encode($data['career']) : null
        ]);
    }
}

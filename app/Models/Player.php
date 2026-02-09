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

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
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
        $sql = "INSERT INTO players (id, name, firstname, lastname, age, birth_date, birth_place, birth_country, nationality, height, weight, photo, injured) 
                VALUES (:id, :name, :firstname, :lastname, :age, :birth_date, :birth_place, :birth_country, :nationality, :height, :weight, :photo, :injured)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), age = VALUES(age), photo = VALUES(photo), 
                injured = VALUES(injured), last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $playerData['id'],
            'name' => $playerData['name'],
            'firstname' => $playerData['firstname'] ?? '',
            'lastname' => $playerData['lastname'] ?? '',
            'age' => $playerData['age'] ?? null,
            'birth_date' => $playerData['birth']['date'] ?? null,
            'birth_place' => $playerData['birth']['place'] ?? null,
            'birth_country' => $playerData['birth']['country'] ?? null,
            'nationality' => $playerData['nationality'] ?? '',
            'height' => $playerData['height'] ?? null,
            'weight' => $playerData['weight'] ?? null,
            'photo' => $playerData['photo'] ?? null,
            'injured' => $playerData['injured'] ?? false
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
    public function getTeams($playerId)
    {
        $sql = "SELECT t.*, s.position, s.number 
                FROM teams t
                JOIN squads s ON t.id = s.team_id
                WHERE s.player_id = ?
                ORDER BY t.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function saveCareer($playerId, $careerData)
    {
        $sql = "INSERT INTO player_career (player_id, team_id, seasons_json) 
                VALUES (:pid, :tid, :seasons)
                ON DUPLICATE KEY UPDATE seasons_json = VALUES(seasons_json), last_updated = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);

        foreach ($careerData as $item) {
            $stmt->execute([
                'pid' => $playerId,
                'tid' => $item['team']['id'],
                'seasons' => json_encode($item['seasons'])
            ]);
        }
    }

    public function getCareer($playerId)
    {
        $sql = "SELECT pc.*, t.name as team_name, t.logo as team_logo, t.country as team_country 
                FROM player_career pc
                JOIN teams t ON pc.team_id = t.id
                WHERE pc.player_id = ?
                ORDER BY JSON_EXTRACT(pc.seasons_json, '$[0]') DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
}

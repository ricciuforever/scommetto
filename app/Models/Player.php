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
                VALUES (:id, :name, :firstname, :lastname, :age, :birth_date, :birth_place, :birth_country, :nationality, :height, :weight, :photo, :injured)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(id) DO UPDATE SET
                    name = EXCLUDED.name, age = EXCLUDED.age, photo = EXCLUDED.photo,
                    injured = EXCLUDED.injured, last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    name = VALUES(name), age = VALUES(age), photo = VALUES(photo),
                    injured = VALUES(injured), last_updated = CURRENT_TIMESTAMP";
        }

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
                VALUES (:team_id, :player_id, :position, :number)";

        if (\App\Services\Database::getInstance()->isSQLite()) {
            $sql .= " ON CONFLICT(team_id, player_id) DO UPDATE SET
                    position = EXCLUDED.position, number = EXCLUDED.number, last_updated = CURRENT_TIMESTAMP";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                    position = VALUES(position), number = VALUES(number), last_updated = CURRENT_TIMESTAMP";
        }

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

    public function getTransfers($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM player_transfers WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'transfers' => json_decode($row['transfers_json'], true),
                'update_date' => $row['update_date'],
                'last_updated' => $row['last_updated']
            ];
        }
        return null;
    }

    public function saveTransfers($playerId, $updateDate, $transfers)
    {
        $stmt = $this->db->prepare("
            INSERT INTO player_transfers (player_id, update_date, transfers_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            update_date = VALUES(update_date), transfers_json = VALUES(transfers_json), last_updated = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $playerId,
            date('Y-m-d H:i:s', strtotime($updateDate)),
            json_encode($transfers)
        ]);
    }

    public function getTrophies($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM player_trophies WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'trophies' => json_decode($row['trophies_json'], true),
                'last_updated' => $row['last_updated']
            ];
        }
        return null;
    }

    public function saveTrophies($playerId, $trophies)
    {
        $stmt = $this->db->prepare("
            INSERT INTO player_trophies (player_id, trophies_json)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
            trophies_json = VALUES(trophies_json), last_updated = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $playerId,
            json_encode($trophies)
        ]);
    }

    public function getSidelined($playerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM player_sidelined WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'sidelined' => json_decode($row['sidelined_json'], true),
                'last_updated' => $row['last_updated']
            ];
        }
        return null;
    }

    public function saveSidelined($playerId, $sidelined)
    {
        $stmt = $this->db->prepare("
            INSERT INTO player_sidelined (player_id, sidelined_json)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
            sidelined_json = VALUES(sidelined_json), last_updated = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $playerId,
            json_encode($sidelined)
        ]);
    }
}

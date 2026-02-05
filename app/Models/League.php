<?php
// app/Models/League.php

namespace App\Models;

use App\Services\Database;
use PDO;

class League
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM leagues WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function save($data)
    {
        $sql = "INSERT INTO leagues (id, name, type, logo, country_name, coverage_json) 
                VALUES (:id, :name, :type, :logo, :country, :coverage)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    type = VALUES(type),
                    logo = VALUES(logo), 
                    country_name = VALUES(country_name),
                    coverage_json = VALUES(coverage_json),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            'id' => $data['league']['id'],
            'name' => $data['league']['name'],
            'type' => $data['league']['type'] ?? 'League',
            'logo' => $data['league']['logo'] ?? null,
            'country' => $data['country']['name'] ?? null,
            'coverage' => json_encode($data['seasons'][0]['coverage'] ?? [])
        ]);

        if (isset($data['seasons'])) {
            $this->saveSeasons($data['league']['id'], $data['seasons']);
        }

        return $res;
    }

    private function saveSeasons($league_id, $seasons)
    {
        $stmt = $this->db->prepare("INSERT INTO league_seasons (league_id, year, is_current, start_date, end_date) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_current = VALUES(is_current), start_date = VALUES(start_date), end_date = VALUES(end_date)");
        foreach ($seasons as $s) {
            $stmt->execute([
                $league_id,
                $s['year'],
                $s['current'] ? 1 : 0,
                $s['start'] ?? null,
                $s['end'] ?? null
            ]);
        }
    }

    public function needsRefresh($hours = 24)
    {
        $stmt = $this->db->query("SELECT MAX(last_updated) as last FROM leagues");
        $row = $stmt->fetch();
        if (!$row['last'])
            return true;
        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }
}

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

    public function supportsPredictions($id)
    {
        $league = $this->getById($id);
        if (!$league || !$league['coverage_json'])
            return true; // Assume true if unknown
        $coverage = json_decode($league['coverage_json'], true);
        return $coverage['predictions'] ?? false;
    }

    public function isBettable($id)
    {
        // Premium leagues are always bettable
        if (in_array($id, \App\Config\Config::PREMIUM_LEAGUES))
            return true;

        $league = $this->getById($id);
        if (!$league)
            return false;
        if (!$league['coverage_json'])
            return false;

        $coverage = json_decode($league['coverage_json'], true);
        return $coverage['odds'] ?? false;
    }

    public function save($data)
    {
        $sql = "INSERT INTO leagues (id, name, type, logo, country, country_name, coverage_json)
                VALUES (:id, :name, :type, :logo, :country, :country_name, :coverage)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    type = VALUES(type),
                    logo = VALUES(logo), 
                    country = VALUES(country),
                    country_name = VALUES(country_name),
                    coverage_json = VALUES(coverage_json),
                    last_updated = CURRENT_TIMESTAMP";

        // Trova la stagione corrente per estrarre la coverage
        $currentSeason = null;
        foreach ($data['seasons'] ?? [] as $s) {
            if ($s['current']) {
                $currentSeason = $s;
                break;
            }
        }
        if (!$currentSeason && !empty($data['seasons'])) {
            $currentSeason = end($data['seasons']);
        }

        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            'id' => $data['league']['id'],
            'name' => $data['league']['name'],
            'type' => $data['league']['type'] ?? 'League',
            'logo' => $data['league']['logo'] ?? null,
            'country' => $data['country']['name'] ?? null,
            'country_name' => $data['country']['name'] ?? null,
            'coverage' => json_encode($currentSeason ? ($currentSeason['coverage'] ?? []) : [])
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

    public function getAll()
    {
        $premiumIds = implode(',', \App\Config\Config::PREMIUM_LEAGUES);
        $sql = "SELECT * FROM leagues 
                ORDER BY 
                    CASE WHEN id IN ($premiumIds) THEN 0 ELSE 1 END,
                    country_name ASC,
                    CASE WHEN type = 'League' THEN 0 ELSE 1 END,
                    name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($hours = 24)
    {
        $stmt = $this->db->query("SELECT MAX(last_updated) as last FROM leagues");
        $row = $stmt->fetch();
        if (!$row || !$row['last'])
            return true;
        return (time() - strtotime($row['last'])) > ($hours * 3600);
    }

    public function deleteAll()
    {
        $this->db->exec("DELETE FROM league_seasons");
        return $this->db->exec("DELETE FROM leagues");
    }
}

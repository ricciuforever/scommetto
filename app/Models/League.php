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

    public function find($filters = [])
    {
        $sql = "SELECT DISTINCT l.* FROM leagues l";
        $params = [];
        $where = ["1=1"];

        if (!empty($filters['team'])) {
            $sql .= " JOIN team_leagues tl ON l.id = tl.league_id";
            $where[] = "tl.team_id = :team";
            $params['team'] = $filters['team'];
        }

        if (!empty($filters['id'])) {
            $where[] = "l.id = :id";
            $params['id'] = $filters['id'];
        }
        if (!empty($filters['name'])) {
            $where[] = "l.name = :name";
            $params['name'] = $filters['name'];
        }
        if (!empty($filters['country'])) {
            $where[] = "l.country_name = :country";
            $params['country'] = $filters['country'];
        }
        if (!empty($filters['code'])) {
            // Nota: abbiamo il codice nazione solo in 'countries',
            // ma l'API Football lo permette come filtro.
            // Possiamo fare una join se necessario o assumere che country_name basti.
            // Per ora semplifichiamo.
        }
        if (!empty($filters['season'])) {
            $sql .= " JOIN league_seasons ls ON l.id = ls.league_id";
            $where[] = "ls.year = :season";
            $params['season'] = $filters['season'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(l.name LIKE :search OR l.country_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['type'])) {
            $where[] = "l.type = :type";
            $params['type'] = $filters['type'];
        }
        if (isset($filters['current']) && $filters['current'] !== '') {
             $sql .= " JOIN league_seasons ls_curr ON l.id = ls_curr.league_id";
             $where[] = "ls_curr.is_current = 1";
        }

        $sql .= " WHERE " . implode(" AND ", $where);

        $premiumIds = implode(',', \App\Config\Config::PREMIUM_LEAGUES);
        $sql .= " ORDER BY
                    CASE WHEN l.id IN ($premiumIds) THEN 0 ELSE 1 END,
                    l.country_name ASC,
                    l.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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

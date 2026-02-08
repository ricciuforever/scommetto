<?php
// app/Models/Venue.php

namespace App\Models;

use App\Services\Database;
use PDO;

class Venue
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save($data)
    {
        if (!isset($data['id']) || !$data['id'])
            return null;

        $sql = "INSERT INTO venues (id, name, address, city, country, capacity, surface, image)
                VALUES (:id, :name, :address, :city, :country, :capacity, :surface, :image)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), address = VALUES(address), city = VALUES(city), 
                    country = VALUES(country),
                    capacity = VALUES(capacity), surface = VALUES(surface), image = VALUES(image),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'surface' => $data['surface'] ?? null,
            'image' => $data['image'] ?? null
        ]);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM venues WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll($limit = 100)
    {
        $stmt = $this->db->prepare("SELECT * FROM venues ORDER BY name ASC LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCountry($country)
    {
        $stmt = $this->db->prepare("SELECT * FROM venues WHERE country = ? ORDER BY name ASC");
        $stmt->execute([$country]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($filters = [])
    {
        $sql = "SELECT * FROM venues WHERE 1=1";
        $params = [];

        if (!empty($filters['id'])) {
            $sql .= " AND id = :id";
            $params['id'] = $filters['id'];
        }
        if (!empty($filters['name'])) {
            $sql .= " AND name = :name";
            $params['name'] = $filters['name'];
        }
        if (!empty($filters['city'])) {
            $sql .= " AND city = :city";
            $params['city'] = $filters['city'];
        }
        if (!empty($filters['country'])) {
            $sql .= " AND country = :country";
            $params['country'] = $filters['country'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR city LIKE :search OR country LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function needsRefresh($id, $hours = 24)
    {
        $stmt = $this->db->prepare("SELECT last_updated FROM venues WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) return true;
        return (time() - strtotime($row['last_updated'])) > ($hours * 3600);
    }
}

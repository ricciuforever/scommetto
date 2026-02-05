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

        $sql = "INSERT INTO venues (id, name, address, city, capacity, surface, image) 
                VALUES (:id, :name, :address, :city, :capacity, :surface, :image)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), address = VALUES(address), city = VALUES(city), 
                    capacity = VALUES(capacity), surface = VALUES(surface), image = VALUES(image),
                    last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'surface' => $data['surface'] ?? null,
            'image' => $data['image'] ?? null
        ]);
    }
}

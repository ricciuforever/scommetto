<?php
// app/GiaNik/GiaNikDatabase.php

namespace App\GiaNik;

use PDO;
use App\Config\Config;

class GiaNikDatabase
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $dbPath = Config::DATA_PATH . 'gianik.sqlite';
        $this->connection = new PDO("sqlite:$dbPath");
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}

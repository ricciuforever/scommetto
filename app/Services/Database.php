<?php
// app/Services/Database.php

namespace App\Services;

use App\Config\Config;
use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $db = Config::get('DB_NAME', 'scommetto_');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ];

        try {
            $this->conn = new PDO($dsn, $user, $pass, $options);
            $this->ensureSchema();
        } catch (PDOException $e) {
            // Fallback to SQLite if MySQL fails (mostly for sandbox environment)
            $dbPath = Config::DATA_PATH . 'scommetto.sqlite';
            try {
                $this->conn = new PDO("sqlite:" . $dbPath);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->ensureSchema();
            } catch (PDOException $e2) {
                throw new \Exception("Database Connection Error: " . $e->getMessage() . " AND SQLite Error: " . $e2->getMessage());
            }
        }
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
        return $this->conn;
    }

    public function isSQLite()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function ensureSchema()
    {
        $isSqlite = $this->isSQLite();
        if ($isSqlite) {
            $query = "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT CHECK(role IN ('admin', 'manager')) DEFAULT 'manager',
                assigned_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'manager') DEFAULT 'manager',
                assigned_agent VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $this->conn->exec($query);
    }
}

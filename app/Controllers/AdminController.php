<?php

namespace App\Controllers;

use App\Config\Config;
use App\Services\Database;
use App\GiaNik\GiaNikDatabase;
use App\Dio\DioDatabase;
use PDO;

class AdminController
{
    private $user;

    public function __construct()
    {
        $this->user = AuthController::check();
    }

    public function index()
    {
        $this->dashboard();
    }

    public function dashboard()
    {
        $user = $this->user;
        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/dashboard.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }

    public function warRoom()
    {
        $user = $this->user;

        // Database configuration
        $databases = [
            'gianik' => [
                'driver' => 'sqlite',
                'path'   => Config::DATA_PATH . 'gianik.sqlite',
                'name'   => '🧠 GiaNik (Brain)',
                'color'  => 'text-green-400',
                'editable' => true
            ],
            'dio'    => [
                'driver' => 'sqlite',
                'path'   => Config::DATA_PATH . 'dio.sqlite',
                'name'   => '⚛️ Dio (Quantum)',
                'color'  => 'text-purple-400',
                'editable' => true
            ],
            'core'   => [
                'driver' => 'mysql',
                'name'   => '⚽ Core Data (MySQL)',
                'color'  => 'text-blue-400',
                'editable' => ($user['role'] === 'admin') // Only Super Admin can edit Core
            ]
        ];

        // Filter databases based on manager assignment
        if ($user['role'] === 'manager' && $user['agent'] !== 'all') {
            $assigned = explode(',', $user['agent']);
            foreach ($databases as $key => $db) {
                if ($key !== 'core' && !in_array($key, $assigned)) {
                    unset($databases[$key]);
                }
            }
        }

        $currentDbKey = $_GET['db'] ?? array_key_first($databases);
        if (!isset($databases[$currentDbKey])) $currentDbKey = array_key_first($databases);

        $dbConfig = $databases[$currentDbKey];

        // Connection
        try {
            if ($dbConfig['driver'] === 'sqlite') {
                $pdo = new PDO("sqlite:" . $dbConfig['path']);
                $pkName = 'rowid';
                $pkAlias = 'rowid as _id';
            } else {
                $pdo = Database::getInstance()->getConnection();
                $pkName = 'id';
                $pkAlias = 'id as _id';
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            die("Errore Criticale DB: " . $e->getMessage());
        }

        $message = "";
        $action = $_POST['action'] ?? ($_GET['action'] ?? 'list');
        $currentTable = $_GET['table'] ?? '';

        // Handle Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConfig['editable']) {
            if ($action === 'update') {
                $id = $_POST['id'];
                $fields = []; $params = [];
                foreach ($_POST['data'] as $col => $val) {
                    if ($col !== '_id') {
                        $fields[] = "`$col` = ?";
                        $params[] = ($val === '') ? null : $val;
                    }
                }
                $params[] = $id;
                $sql = "UPDATE `$currentTable` SET " . implode(', ', $fields) . " WHERE $pkName = ?";
                try {
                    $pdo->prepare($sql)->execute($params);
                    $message = "✅ Record aggiornato con successo.";
                } catch (\Exception $e) { $message = "❌ Errore Update: " . $e->getMessage(); }
            }
        }

        if ($action === 'delete' && isset($_GET['id']) && $dbConfig['editable']) {
            try {
                $pdo->prepare("DELETE FROM `$currentTable` WHERE $pkName = ?")->execute([$_GET['id']]);
                $message = "🗑️ Record eliminato.";
            } catch (\Exception $e) { $message = "❌ Errore Delete: " . $e->getMessage(); }
        }

        // Fetch Tables
        if ($dbConfig['driver'] === 'sqlite') {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            if (Database::getInstance()->isSQLite()) {
                 $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                 $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // Security: Whitelist Table Name
        if ($currentTable && !in_array($currentTable, $tables)) {
            die("Access Denied: Invalid Table");
        }

        if (!$currentTable && !empty($tables)) $currentTable = $tables[0];

        // Fetch Data
        $rows = [];
        $totalRows = 0;
        $search = $_GET['search'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if ($currentTable) {
            $whereClause = "";
            if ($search) {
                if ($dbConfig['driver'] === 'sqlite' || Database::getInstance()->isSQLite()) {
                    $colsInfo = $pdo->query("PRAGMA table_info(`$currentTable`)")->fetchAll(PDO::FETCH_ASSOC);
                    $columns = array_column($colsInfo, 'name');
                } else {
                    $columns = $pdo->query("DESCRIBE `$currentTable`")->fetchAll(PDO::FETCH_COLUMN);
                }
                $searchParts = [];
                $searchableCols = array_slice($columns, 0, 8);
                foreach ($searchableCols as $col) {
                    $searchParts[] = "`$col` LIKE " . $pdo->quote("%$search%");
                }
                $whereClause = "WHERE " . implode(" OR ", $searchParts);
            }

            try {
                $totalRows = $pdo->query("SELECT COUNT(*) FROM `$currentTable` $whereClause")->fetchColumn();

                $orderBy = "";
                if ($dbConfig['driver'] === 'sqlite' || Database::getInstance()->isSQLite()) {
                    $orderBy = "ORDER BY rowid DESC";
                } else {
                    // Try to order by id if exists
                    $cols = $pdo->query("DESCRIBE `$currentTable`")->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array('id', $cols)) $orderBy = "ORDER BY id DESC";
                }

                $sql = "SELECT $pkAlias, * FROM `$currentTable` $whereClause $orderBy LIMIT $limit OFFSET $offset";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $message = "⚠️ Errore Query: " . $e->getMessage();
            }
        }

        $totalPages = ceil($totalRows / $limit);

        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/war_room.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }

    public function strategy()
    {
        $user = $this->user;
        $message = "";

        // Determine which agent we are configuring
        $agents = [];
        if ($user['role'] === 'admin' || $user['agent'] === 'all') {
            $agents = ['gianik', 'dio'];
        } else {
            $agents = explode(',', $user['agent']);
        }

        $currentAgent = $_GET['agent'] ?? $agents[0];
        if (!in_array($currentAgent, $agents)) $currentAgent = $agents[0];

        $db = ($currentAgent === 'gianik') ? GiaNikDatabase::getInstance()->getConnection() : DioDatabase::getInstance()->getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST['config'] as $key => $val) {
                $stmt = $db->prepare("INSERT INTO system_state (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$key, $val]);
            }
            $message = "✅ Configurazione salvata con successo.";
        }

        $config = $db->query("SELECT key, value FROM system_state")->fetchAll(PDO::FETCH_KEY_PAIR);

        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/strategy.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }

    public function users()
    {
        if (!AuthController::isAdmin()) {
            header('Location: /admin');
            exit;
        }

        $db = Database::getInstance()->getConnection();
        $message = "";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'create') {
                $username = $_POST['username'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                $agent = $_POST['agent'];

                try {
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, assigned_agent) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $password, $role, $agent]);
                    $message = "✅ Utente creato.";
                } catch (\Exception $e) { $message = "❌ Errore: " . $e->getMessage(); }
            } elseif ($action === 'delete') {
                $id = $_POST['id'];
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                $message = "🗑️ Utente eliminato.";
            }
        }

        $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/users.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }
}

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
                'path' => Config::DATA_PATH . 'gianik.sqlite',
                'name' => '🧠 GiaNik (Brain)',
                'color' => 'text-green-400',
                'editable' => true
            ],
            'dio' => [
                'driver' => 'sqlite',
                'path' => Config::DATA_PATH . 'dio.sqlite',
                'name' => '⚛️ Dio (Quantum)',
                'color' => 'text-purple-400',
                'editable' => true
            ],
            'core' => [
                'driver' => 'mysql',
                'name' => '⚽ Core Data (MySQL)',
                'color' => 'text-blue-400',
                'editable' => ($user['role'] === 'admin') // Only Super Admin can edit Core
            ]
        ];

        // Filter databases based on manager assignment
        if ($user['role'] === 'manager') {
            // Manager cannot see 'core'
            unset($databases['core']);

            if ($user['agent'] !== 'all') {
                $assigned = explode(',', $user['agent']);
                foreach ($databases as $key => $db) {
                    if (!in_array($key, $assigned)) {
                        unset($databases[$key]);
                    }
                }
            }
        }

        $currentDbKey = $_GET['db'] ?? array_key_first($databases);
        if (!isset($databases[$currentDbKey]))
            $currentDbKey = array_key_first($databases);

        $dbConfig = $databases[$currentDbKey];

        // Connection
        try {
            if ($dbConfig['driver'] === 'sqlite') {
                $pdo = new PDO("sqlite:" . $dbConfig['path']);
            } else {
                $pdo = Database::getInstance()->getConnection();
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            die("Errore Criticale DB: " . $e->getMessage());
        }

        $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

        $message = "";
        $action = $_POST['action'] ?? ($_GET['action'] ?? 'list');
        $currentTable = $_GET['table'] ?? '';

        // Fetch Tables first for whitelisting
        if ($isSqlite) {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        }

        // Security: Whitelist Table Name
        if ($currentTable && !in_array($currentTable, $tables)) {
            die("Access Denied: Invalid Table");
        }
        if (!$currentTable && !empty($tables))
            $currentTable = $tables[0];

        // Fetch valid columns and Primary Key
        $validColumns = [];
        $pkName = $isSqlite ? 'rowid' : 'id';
        $pkAlias = $isSqlite ? 'rowid as _id' : 'id as _id';

        if ($currentTable) {
            if ($isSqlite) {
                $colsInfo = $pdo->query("PRAGMA table_info(`$currentTable`)")->fetchAll(PDO::FETCH_ASSOC);
                $validColumns = array_column($colsInfo, 'name');
            } else {
                try {
                    $validColumns = $pdo->query("DESCRIBE `$currentTable`")->fetchAll(PDO::FETCH_COLUMN);
                    // Try to detect real PK for MySQL
                    $pkInfo = $pdo->query("SHOW KEYS FROM `$currentTable` WHERE Key_name = 'PRIMARY'")->fetch();
                    if ($pkInfo) {
                        $pkName = $pkInfo['Column_name'];
                        $pkAlias = "`$pkName` as _id";
                    }
                } catch (\Exception $e) {
                    // Fallback for column detection
                    $stmt = $pdo->query("SELECT * FROM `$currentTable` LIMIT 0");
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        $validColumns[] = $meta['name'];
                    }
                }

                // Final check for PK in validColumns for MySQL
                if (!in_array($pkName, $validColumns) && !empty($validColumns)) {
                    $pkName = $validColumns[0];
                    $pkAlias = "`$pkName` as _id";
                }
            }
        }

        // Handle Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConfig['editable'] && $currentTable) {
            if ($action === 'update') {
                $id = $_POST['id'];
                $fields = [];
                $params = [];
                foreach ($_POST['data'] as $col => $val) {
                    // Critical Whitelisting: Ensure column exists in table
                    if ($col !== '_id' && in_array($col, $validColumns)) {
                        $fields[] = "`$col` = ?";
                        $params[] = ($val === '') ? null : $val;
                    }
                }
                if (!empty($fields)) {
                    $params[] = $id;
                    $sql = "UPDATE `$currentTable` SET " . implode(', ', $fields) . " WHERE `$pkName` = ?";
                    try {
                        $pdo->prepare($sql)->execute($params);
                        $message = "✅ Record aggiornato con successo.";
                    } catch (\Exception $e) {
                        $message = "❌ Errore Update: " . $e->getMessage();
                    }
                }
            }
        }

        if ($action === 'delete' && isset($_GET['id']) && $dbConfig['editable'] && $currentTable) {
            try {
                $pdo->prepare("DELETE FROM `$currentTable` WHERE `$pkName` = ?")->execute([$_GET['id']]);
                $message = "🗑️ Record eliminato.";
            } catch (\Exception $e) {
                $message = "❌ Errore Delete: " . $e->getMessage();
            }
        }

        // Fetch Data
        $rows = [];
        $totalRows = 0;
        $search = $_GET['search'] ?? '';
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if ($currentTable) {
            $whereClause = "";
            if ($search) {
                $searchParts = [];
                $searchableCols = array_slice($validColumns, 0, 8);
                foreach ($searchableCols as $col) {
                    $searchParts[] = "`$col` LIKE " . $pdo->quote("%$search%");
                }
                $whereClause = "WHERE " . implode(" OR ", $searchParts);
            }

            try {
                $totalRows = $pdo->query("SELECT COUNT(*) FROM `$currentTable` $whereClause")->fetchColumn();

                $sort = $_GET['sort'] ?? '';
                $order = strtoupper($_GET['order'] ?? 'DESC');
                if (!in_array($order, ['ASC', 'DESC']))
                    $order = 'DESC';

                $orderBy = "";
                if ($sort && in_array($sort, $validColumns)) {
                    $orderBy = "ORDER BY `$sort` $order";
                } elseif ($pkName && in_array($pkName, $validColumns)) {
                    $orderBy = "ORDER BY `$pkName` DESC";
                } elseif ($isSqlite) {
                    $orderBy = "ORDER BY rowid DESC";
                }

                // Query construction
                if ($isSqlite) {
                    $sql = "SELECT $pkAlias, * FROM `$currentTable` $whereClause $orderBy LIMIT $limit OFFSET $offset";
                } else {
                    $sql = "SELECT `$currentTable`.*, $pkAlias FROM `$currentTable` $whereClause $orderBy LIMIT $limit OFFSET $offset";
                }

                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $message = "⚠️ Errore Query: " . $e->getMessage();
            }
        }

        $totalPages = ceil($totalRows / $limit);

        $tab = $_GET['tab'] ?? 'data';

        require __DIR__ . '/../Views/admin/layout/header.php';
        if ($tab === 'intelligence' && $currentDbKey === 'gianik') {
            (new \App\GiaNik\Controllers\BrainController())->index();
        } elseif ($tab === 'quantum' && $currentDbKey === 'dio') {
            (new \App\Dio\Controllers\DioQuantumController())->index();
        } else {
            require __DIR__ . '/../Views/admin/war_room.php';
        }
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
        if (!in_array($currentAgent, $agents))
            $currentAgent = $agents[0];

        $db = ($currentAgent === 'gianik') ? GiaNikDatabase::getInstance()->getConnection() : DioDatabase::getInstance()->getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST['config'] as $key => $val) {
                // Handle array inputs (e.g. checkboxes)
                if (is_array($val)) {
                    $val = implode(',', $val);
                }

                // Validation for min_stake
                if ($key === 'min_stake' && (float) $val < 2.0) {
                    $val = '2.00';
                }

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
                } catch (\Exception $e) {
                    $message = "❌ Errore: " . $e->getMessage();
                }
            } elseif ($action === 'delete') {
                $id = $_POST['id'];
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                $message = "🗑️ Utente eliminato.";
            } elseif ($action === 'update_password') {
                $id = $_POST['id'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password, $id]);
                $message = "✅ Password aggiornata.";
            }
        }

        $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/users.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }

    public function systemSettings()
    {
        if (!AuthController::isAdmin()) {
            header('Location: /admin');
            exit;
        }

        $message = "";
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newSettings = [
                'simulation_mode' => isset($_POST['simulation_mode']),
                'initial_bankroll' => (float) $_POST['initial_bankroll']
            ];

            file_put_contents(Config::SETTINGS_FILE, json_encode($newSettings, JSON_PRETTY_PRINT));
            $message = "✅ Impostazioni di sistema aggiornate.";
        }

        $settings = Config::getSettings();

        require __DIR__ . '/../Views/admin/layout/header.php';
        require __DIR__ . '/../Views/admin/system.php';
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }
}

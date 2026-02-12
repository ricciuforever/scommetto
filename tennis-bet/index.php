<?php
// tennis-bet/index.php

// Auto-loader for our classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'TennisApp\\') === 0) {
        $path = str_replace(['TennisApp\\', '\\'], ['', '/'], $class);
        $file = __DIR__ . '/app/' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use TennisApp\Config\TennisConfig;
use TennisApp\Controllers\TennisController;

TennisConfig::init();
// Auto-initialize database/balance
require_once __DIR__ . '/init_db.php';

$controller = new TennisController();
$action = $_GET['action'] ?? 'index';

if ($action === 'analyze') {
    $id = $_GET['id'] ?? null;
    $name = $_GET['name'] ?? null;
    header('Content-Type: application/json');
    if ($id && $name) {
        echo json_encode($controller->analyzeEvent($id, $name));
    } else {
        echo json_encode(["error" => "Missing parameters"]);
    }
    exit;
}

// Default Action: Index
$data = $controller->index();
extract($data);
require_once __DIR__ . '/app/Views/dashboard.php';

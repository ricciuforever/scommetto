<?php
// bootstrap.php

require_once __DIR__ . '/app/Config/Config.php';

use App\Config\Config;

Config::init();

// Enable Error Logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', Config::LOG_FILE);

// Simple PSR-4 style autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Ensure directories exist
if (!is_dir(Config::DATA_PATH)) {
    mkdir(Config::DATA_PATH, 0777, true);
}
if (!is_dir(Config::LOGS_PATH)) {
    mkdir(Config::LOGS_PATH, 0777, true);
}

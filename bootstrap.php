<?php
// bootstrap.php

require_once __DIR__ . '/app/Config/Config.php';

use App\Config\Config;

Config::init();

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

// Ensure data directory exists
if (!is_dir(Config::DATA_PATH)) {
    mkdir(Config::DATA_PATH, 0777, true);
}

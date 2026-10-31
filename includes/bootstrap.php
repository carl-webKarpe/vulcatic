<?php
// Loaded at the top of every page: config, session, database, helpers.

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    exit('Missing config/config.php. Copy config/config.example.php to config/config.php and edit it.');
}
$config = require $configFile;

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Manila');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

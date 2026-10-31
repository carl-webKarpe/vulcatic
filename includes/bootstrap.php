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
    // Live site: log the details, show people a friendly page.
    set_exception_handler(function (Throwable $e) {
        error_log('Vulcatic: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        readfile(__DIR__ . '/error_page.html');
    });
}

date_default_timezone_set('Asia/Manila');

// Stay logged in for 30 days (an installed phone app shouldn't log you out daily).
// Sessions live in our own folder so the server's cleanup doesn't delete them early.
const SESSION_DAYS = 30;
$sessionDir = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionDir)) @mkdir($sessionDir, 0700, true);
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
    ini_set('session.gc_maxlifetime', (string) (SESSION_DAYS * 86400));
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '200');
}
session_name('vulcatic');
session_set_cookie_params([
    'lifetime' => SESSION_DAYS * 86400,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

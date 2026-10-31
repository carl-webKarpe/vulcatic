<?php
// Single shared PDO connection. Call db() anywhere to get it.

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $c = $config['db'];
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            exit($config['app']['debug']
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Database connection failed. Please try again later.');
        }
    }
    return $pdo;
}

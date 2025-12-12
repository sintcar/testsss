<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO
{
    static $pdo;
    global $config;
    if ($pdo) {
        return $pdo;
    }
    if (!$config['db']['host'] || !$config['db']['name']) {
        throw new RuntimeException('Database configuration is missing.');
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['name'], $config['db']['charset']);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
    return $pdo;
}

<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

function databaseConnection(): PDO
{
    $config = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3301',
        'name' => getenv('DB_NAME') ?: 'workshop',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ];

    $localConfig = dirname(__DIR__, 2) . '/config/database.local.php';
    if (is_file($localConfig)) {
        $localSettings = require $localConfig;
        if (is_array($localSettings)) {
            $config = array_merge($config, $localSettings);
        }
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']);
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

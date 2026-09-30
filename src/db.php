<?php
declare(strict_types=1);

/**
 * Returns a single shared PDO instance.
 * Uses real prepared statements, exceptions on error.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config()['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['name']
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        // Force this connection to UTC so NOW(), CURRENT_TIMESTAMP,
        // and any DEFAULT CURRENT_TIMESTAMP columns write UTC.
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Never expose the raw PDO message in production.
        if (config()['app']['debug']) {
            throw $e;
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable.');
    }

    return $pdo;
}
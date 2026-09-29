<?php
declare(strict_types=1);

/**
 * Loads .env from the project root and returns a config array.
 * Called once by src/bootstrap.php.
 */

function load_env(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException("Environment file not found: {$path}");
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Must contain "="
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes if present
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $vars[$key] = $value;
    }

    return $vars;
}

function config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $root    = dirname(__DIR__);
    $envPath = $root . DIRECTORY_SEPARATOR . '.env';
    $env     = load_env($envPath);

    $config = [
        'app' => [
            'env'   => $env['APP_ENV']   ?? 'production',
            'debug' => filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL),
            'url'   => $env['APP_URL']   ?? '',
            'root'  => $root,
        ],
        'db' => [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => (int)($env['DB_PORT'] ?? 3306),
            'name' => $env['DB_NAME'] ?? '',
            'user' => $env['DB_USER'] ?? '',
            'pass' => $env['DB_PASS'] ?? '',
        ],
        'session' => [
            'name'     => $env['SESSION_NAME']     ?? 'mj_session',
            'lifetime' => (int)($env['SESSION_LIFETIME'] ?? 2592000),
        ],
        'imagekit' => [
            'url_endpoint' => $env['IMAGEKIT_URL_ENDPOINT'] ?? '',
            'public_key'   => $env['IMAGEKIT_PUBLIC_KEY']   ?? '',
            'private_key'  => $env['IMAGEKIT_PRIVATE_KEY']  ?? '',
        ],
    ];

    return $config;
}
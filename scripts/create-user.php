<?php
declare(strict_types=1);

/**
 * CLI account creator.
 *
 * Usage:
 *   php scripts/create-user.php <username> <role>
 *
 * Prompts for a password twice (hidden input where possible).
 * Roles: admin | user
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

// Parse args
$username = $argv[1] ?? null;
$role     = $argv[2] ?? null;

if (!$username || !$role) {
    fwrite(STDERR, "Usage: php scripts/create-user.php <username> <role>\n");
    fwrite(STDERR, "Roles: admin | user\n");
    exit(1);
}

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    fwrite(STDERR, "Username must be 3-50 chars, letters/digits/underscore only.\n");
    exit(1);
}

if (!in_array($role, ['admin', 'user'], true)) {
    fwrite(STDERR, "Role must be 'admin' or 'user'.\n");
    exit(1);
}

// Does the user already exist?
$stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    fwrite(STDERR, "User already exists: {$username}\n");
    exit(1);
}

// Prompt for password
echo "Password for {$username}: ";
$pw1 = read_password();
echo "\nConfirm password: ";
$pw2 = read_password();
echo "\n";

if ($pw1 !== $pw2) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

if (strlen($pw1) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$hash = hash_password($pw1);

$stmt = db()->prepare(
    'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
);
$stmt->execute([$username, $hash, $role]);

echo "Created user '{$username}' with role '{$role}' (id=" . db()->lastInsertId() . ").\n";
echo "Algorithm: " . (password_algo() === PASSWORD_ARGON2ID ? 'argon2id' : 'bcrypt') . "\n";


/**
 * Read a password from stdin, hiding it if possible.
 */
function read_password(): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: try to hide with `stty` unavailable — fall back to plain.
        // PowerShell users get plain echo. Simple and cross-platform.
        $line = fgets(STDIN);
        return rtrim((string)$line, "\r\n");
    }

    // Unix: disable echo
    shell_exec('stty -echo');
    $line = fgets(STDIN);
    shell_exec('stty echo');
    return rtrim((string)$line, "\r\n");
}
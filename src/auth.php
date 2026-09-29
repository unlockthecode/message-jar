<?php
declare(strict_types=1);

/**
 * Authentication and authorization.
 *
 * Session shape (once logged in):
 *   $_SESSION['user_id']       int
 *   $_SESSION['username']      string
 *   $_SESSION['role']          'admin' | 'user'
 *   $_SESSION['logged_in_at']  int (unix timestamp)
 *   $_SESSION['last_seen_at']  int (unix timestamp, updated per request)
 */

const LOGIN_MAX_ATTEMPTS = 5;         // per window
const LOGIN_WINDOW_SEC   = 900;       // 15 minutes
const SESSION_IDLE_SEC   = 2592000;   // 30 days (matches .env SESSION_LIFETIME)
const SESSION_ABSOLUTE_SEC = 7776000; // 90 days — hard cap

/**
 * Choose the best available password hashing algorithm.
 */
function password_algo(): string|int
{
    if (defined('PASSWORD_ARGON2ID')) {
        return PASSWORD_ARGON2ID;
    }
    return PASSWORD_BCRYPT;
}

/**
 * Create a password hash with our chosen algorithm.
 */
function hash_password(string $plain): string
{
    return password_hash($plain, password_algo());
}

/**
 * Verify a password and transparently upgrade the hash if needed.
 * Returns true on success, false otherwise.
 */
function verify_password(string $plain, string $hash, int $userId): bool
{
    if (!password_verify($plain, $hash)) {
        return false;
    }

    if (password_needs_rehash($hash, password_algo())) {
        $new = hash_password($plain);
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$new, $userId]);
    }

    return true;
}

/**
 * Attempt a login. Returns [success, user|null].
 * Records the attempt for rate limiting.
 */
function attempt_login(string $username, string $password): array
{
    $ip = client_ip();

    // 1. Rate limit check
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS n FROM login_attempts
         WHERE ip_address = ? AND success = 0
           AND attempted_at > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, LOGIN_WINDOW_SEC]);
    $failures = (int)($stmt->fetch()['n'] ?? 0);

    if ($failures >= LOGIN_MAX_ATTEMPTS) {
        record_login_attempt($ip, $username, false);
        return [false, 'too_many_attempts'];
    }

    // 2. Look up the user
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 3. Verify password (always run a hash to avoid user-enumeration timing)
    $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $hash = $user['password_hash'] ?? $dummyHash;
    $ok   = verify_password($password, $hash, (int)($user['id'] ?? 0));

    if (!$user || !$ok) {
        record_login_attempt($ip, $username, false);
        return [false, 'invalid'];
    }

    // 4. Success
    record_login_attempt($ip, $username, true);
    login_user($user);
    return [true, null];
}

/**
 * Put the user into the session. Called only after successful verify.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    csrf_rotate();

    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['username']     = (string)$user['username'];
    $_SESSION['role']         = (string)$user['role'];
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_seen_at'] = time();

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
}

function record_login_attempt(string $ip, ?string $username, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$ip, $username, $success ? 1 : 0]);
}

/**
 * Log the user out and destroy the session.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

/**
 * Is anyone logged in right now? Also enforces timeouts.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $now = time();

    // Absolute cap
    if (($now - (int)($_SESSION['logged_in_at'] ?? 0)) > SESSION_ABSOLUTE_SEC) {
        logout_user();
        return null;
    }

    // Sliding idle window
    if (($now - (int)($_SESSION['last_seen_at'] ?? 0)) > SESSION_IDLE_SEC) {
        logout_user();
        return null;
    }

    $_SESSION['last_seen_at'] = $now;

    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => (string)$_SESSION['username'],
        'role'     => (string)$_SESSION['role'],
    ];
}

/**
 * Require a logged-in user. Redirect to login if not.
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/login.php');
    }
    return $user;
}

/**
 * Require an admin. 403 if a non-admin tries.
 */
function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        abort(403, 'Forbidden.');
    }
    return $user;
}
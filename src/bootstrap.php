<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Every entry point (public/*.php, public/admin/*.php, scripts/*.php)
 * starts by requiring this file. It:
 *   1. Loads .env into a config array.
 *   2. Configures error reporting (dev vs production).
 *   3. Forces HTTPS in production.
 *   4. Starts a hardened session.
 *   5. Sends security headers (CSP and friends).
 *   6. Loads the core helper modules.
 *
 * Order matters — see comments.
 */

// ── 1. Configuration ────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
$cfg = config();

// Convenience booleans. Use APP_IS_PROD / APP_IS_DEV instead of
// comparing $cfg['app']['env'] everywhere.
define('APP_IS_PROD', ($cfg['app']['env'] === 'production'));
define('APP_IS_DEV',  !APP_IS_PROD);


// ── 2. Error reporting ──────────────────────────────────────
error_reporting(E_ALL);

if (APP_IS_PROD) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $cfg['app']['root'] . '/logs/php-error.log');

    // Anything that escapes try/catch becomes a generic 500.
    // The real error goes to the log, never to the browser.
    set_exception_handler(function (Throwable $e): void {
        error_log(sprintf(
            'Uncaught %s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Something went wrong. Please try again later.';
        exit;
    });
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', $cfg['app']['root'] . '/logs/php-error.log');
}


// ── 3. Timezone ─────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');


// ── 4. HTTPS enforcement (production only) ──────────────────
if (APP_IS_PROD) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if (!$https) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';

        // Only redirect to hosts that look like real hostnames.
        // Prevents open-redirect via a spoofed Host header.
        if (preg_match('/^[a-zA-Z0-9.\-]+(?::\d+)?$/', $host)) {
            header('Location: https://' . $host . $uri, true, 301);
            exit;
        }
        // If the host is nonsense, refuse rather than redirect somewhere odd.
        http_response_code(400);
        exit('Bad request.');
    }
}


// ── 5. Session ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Session ini settings — must be set BEFORE session_start().
    ini_set('session.use_strict_mode', '1');          // reject unknown IDs
    ini_set('session.use_only_cookies', '1');         // never via URL
    ini_set('session.cookie_httponly', '1');          // JS can't read it
    ini_set('session.sid_length', '48');              // longer session ID
    ini_set('session.sid_bits_per_character', '5');   // more entropy per char

    session_name($cfg['session']['name']);

    // Determine whether this request is over HTTPS. On InfinityFree
    // (and behind most reverse proxies) the origin sees HTTP; the
    // real scheme arrives in X-Forwarded-Proto.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // session cookie; clears on browser close
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,    // HTTPS-only in production
        'httponly' => true,
        'samesite' => 'Lax',      // strong CSRF defense-in-depth
    ]);

    session_start();
}


// ── 6. Security headers ─────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

// Content Security Policy — allow only what the site actually uses.
$csp = implode('; ', [
    "default-src 'none'",
    "script-src 'self'",
    "style-src 'self'",
    "img-src 'self' https://ik.imagekit.io data:",
    "font-src 'self'",
    "connect-src 'self'",
    "frame-src https://www.youtube-nocookie.com",
    "frame-ancestors 'none'",
    "form-action 'self'",
    "base-uri 'self'",
    "object-src 'none'",
]);
header('Content-Security-Policy: ' . $csp);

// HSTS — DO NOT enable until HTTPS is confirmed working in production.
// Once a browser sees it, it refuses HTTP for the max-age duration.
// Enable it the day AFTER your first successful HTTPS deploy, not the
// same day. See Phase 13 for the exact procedure.
//
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');


// ── 7. Core modules ─────────────────────────────────────────
// Order matters. security.php defines e(), abort(), redirect() and is
// used by almost everything else. csrf.php uses security helpers.
// auth.php uses both. The rest are independent helpers.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jars.php';
require_once __DIR__ . '/youtube.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/link_labels.php';
require_once __DIR__ . '/admin_stats.php';
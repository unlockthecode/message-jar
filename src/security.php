<?php
declare(strict_types=1);

/**
 * Generic security helpers: output escaping, request helpers,
 * and header setup.
 */

/**
 * Escape a value for safe HTML output.
 * Use this on EVERY variable that gets echoed into HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Fetch the client IP safely. Behind proxies this may be spoofable,
 * but for our purposes (rate limiting) it's good enough.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Return true if the current request is a POST.
 */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Redirect and stop execution.
 */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

/**
 * Send a simple 4xx and stop.
 */
function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    if ($message !== '') {
        // Plain text is fine here — pages should not reach this in normal flow.
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit;
}

/**
 * Return true if the URL is a valid HTTPS URL.
 * Used by both the validator and the renderer, so behavior is identical.
 */
function is_https_url(?string $url): bool
{
    if ($url === null) return false;
    $url = trim($url);
    if ($url === '') return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
}

/**
 * "3 minutes ago", "2 hours ago", "yesterday", "Apr 12".
 * Not localized — good enough for an admin panel.
 */
function human_time_ago(string $dbDatetime): string
{
    $ts   = strtotime($dbDatetime);
    if ($ts === false) return '';
    $diff = time() - $ts;

    if ($diff < 60)          return 'just now';
    if ($diff < 3600)        return (int)($diff / 60) . ' min ago';
    if ($diff < 86400)       return (int)($diff / 3600) . ' h ago';
    if ($diff < 2 * 86400)   return 'yesterday';
    if ($diff < 7 * 86400)   return (int)($diff / 86400) . ' days ago';
    return date('M j', $ts);
}
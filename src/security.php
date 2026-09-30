<?php
declare(strict_types=1);

/**
 * Generic security helpers: output escaping, request helpers,
 * CSS value helpers, time helpers, and header-related utilities.
 *
 * TIMEZONE POLICY:
 *   - Everything is stored in UTC in the database.
 *   - PHP's default timezone is UTC (set in bootstrap.php).
 *   - Display times use display_tz(), which prefers the visitor's
 *     browser timezone (from a cookie) and falls back to DISPLAY_TZ.
 *   - Scheduling input (unlock/expires) is anchored to DISPLAY_TZ,
 *     never the visitor's timezone.
 *   - Use format_display_time() for absolute output,
 *     now_display() for "current time in the display timezone",
 *     and human_time_ago() for relative times.
 */

/**
 * Escape a value for safe HTML output.
 * Use this on EVERY variable that gets echoed into HTML text or
 * attribute values.
 *
 * Note: do NOT use this on CSS color values inside a style
 * attribute — htmlspecialchars() can encode '#' and break the
 * CSS parser silently. Use css_hex_color() for that instead.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Safely output a hex color for use inside a CSS custom property.
 *
 * Why not use e()? htmlspecialchars() may encode '#' as &#35; in
 * some PHP configurations. getAttribute('style') decodes it back
 * to '#' for display, but the CSS parser sees the pre-decoded
 * value and rejects the whole declaration — silently.
 *
 * Hex colors contain only characters that are safe in both HTML
 * attributes and CSS values (#, 0-9, a-f, A-F), so we validate
 * against a strict pattern and emit raw.
 *
 * Returns the fallback if the input isn't a valid #rrggbb.
 */
function css_hex_color(?string $color, string $fallback = '#ff8fab'): string
{
    if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return $color;
    }
    return $fallback;
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
 * Determine the timezone to use for display.
 *
 * Priority:
 *   1. The visitor's browser timezone, stored in the visitor_tz
 *      cookie (set by assets/js/jar.js on first page load).
 *   2. The DISPLAY_TZ constant (from .env; default Asia/Manila).
 *
 * The cookie value is validated against PHP's list of known
 * timezone identifiers, so a malicious cookie can't inject
 * arbitrary values.
 */
function display_tz(): string
{
    $candidate = (string)($_COOKIE['visitor_tz'] ?? '');
    if ($candidate !== '' && in_array($candidate, DateTimeZone::listIdentifiers(), true)) {
        return $candidate;
    }
    return DISPLAY_TZ;
}

/**
 * Format a UTC timestamp (MySQL DATETIME string) for display.
 * Returns '' for null/empty input.
 *
 * $dbValue must be a MySQL DATETIME in UTC (e.g. "2026-09-30 08:15:00").
 * $format is any PHP date() format string.
 */
function format_display_time(?string $dbValue, string $format = 'M j, Y H:i'): string
{
    if ($dbValue === null || $dbValue === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($dbValue, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return '';
    }

    $dt = $dt->setTimezone(new DateTimeZone(display_tz()));
    return $dt->format($format);
}

/**
 * Return "now" in the display timezone as a DateTimeImmutable.
 */
function now_display(): DateTimeImmutable
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(display_tz()));
}

/**
 * "just now", "3 min ago", "2 h ago", "yesterday", "Apr 12".
 *
 * Input must be a MySQL DATETIME string stored in UTC.
 *
 * Note: relative times are timezone-independent. "5 minutes ago"
 * is the same duration in every timezone. We only need the
 * visitor timezone for the fallback that shows a calendar date
 * ("Apr 12").
 */
function human_time_ago(string $dbDatetime): string
{
    if ($dbDatetime === '') {
        return '';
    }

    try {
        $then = new DateTimeImmutable($dbDatetime, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return '';
    }

    $now  = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 0)         return 'just now';
    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return (int)($diff / 60) . ' min ago';
    if ($diff < 86400)     return (int)($diff / 3600) . ' h ago';
    if ($diff < 2 * 86400) return 'yesterday';
    if ($diff < 7 * 86400) return (int)($diff / 86400) . ' days ago';

    return format_display_time($dbDatetime, 'M j');
}
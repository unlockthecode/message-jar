<?php
declare(strict_types=1);

/**
 * Parse a YouTube URL into an 11-character video ID.
 *
 * Accepts:
 *   https://www.youtube.com/watch?v=XXXXXXXXXXX
 *   https://youtube.com/watch?v=XXXXXXXXXXX
 *   https://m.youtube.com/watch?v=XXXXXXXXXXX
 *   https://youtu.be/XXXXXXXXXXX
 *   https://www.youtube.com/shorts/XXXXXXXXXXX
 *   https://www.youtube.com/embed/XXXXXXXXXXX
 *   https://www.youtube.com/live/XXXXXXXXXXX
 *
 * Returns the ID, or null if not a valid YouTube URL.
 * Never returns raw HTML, never trusts the input.
 */

function youtube_extract_id(?string $url): ?string
{
    if ($url === null) {
        return null;
    }
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    // Bare 11-char ID pasted directly? Accept it.
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }

    // Must be a valid URL, https only.
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
        return null;
    }

    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';

    // Whitelist of YouTube hosts. Do NOT accept arbitrary hosts.
    $allowedHosts = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtu.be', 'www.youtu.be',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];
    if (!in_array($host, $allowedHosts, true)) {
        return null;
    }

    // youtu.be/ID
    if ($host === 'youtu.be' || $host === 'www.youtu.be') {
        $id = ltrim($path, '/');
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    // /watch?v=ID
    if ($path === '/watch') {
        if (empty($parts['query'])) {
            return null;
        }
        parse_str($parts['query'], $q);
        $id = $q['v'] ?? '';
        return preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$id) ? $id : null;
    }

    // /shorts/ID, /embed/ID, /live/ID
    if (preg_match('#^/(shorts|embed|live)/([A-Za-z0-9_-]{11})/?$#', $path, $m)) {
        return $m[2];
    }

    return null;
}
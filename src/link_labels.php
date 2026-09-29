<?php
declare(strict_types=1);

/**
 * Human-friendly labels for well-known external link hosts.
 * Falls back to the bare hostname if no entry matches.
 *
 * Deliberately NOT using an external library — this is a
 * dozen lines and avoids a dependency.
 */
function link_label_for(string $url): string
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $path = (string)parse_url($url, PHP_URL_PATH);

    // Strip leading "www."
    $bare = preg_replace('/^www\./', '', $host) ?? $host;

    // Exact host matches
    $map = [
        'open.spotify.com'  => 'Spotify',
        'spotify.com'       => 'Spotify',
        'youtube.com'       => 'YouTube',
        'youtu.be'          => 'YouTube',
        'music.youtube.com' => 'YouTube Music',
        'instagram.com'     => 'Instagram',
        'twitter.com'       => 'X (Twitter)',
        'x.com'             => 'X (Twitter)',
        'tiktok.com'        => 'TikTok',
        'facebook.com'      => 'Facebook',
        'pinterest.com'     => 'Pinterest',
        'discord.gg'        => 'Discord invite',
        'github.com'        => 'GitHub',
        'letterboxd.com'    => 'Letterboxd',
        'goodreads.com'     => 'Goodreads',
    ];

    if (isset($map[$bare])) {
        return $map[$bare];
    }

    // Subdomain-aware: any *.spotify.com is Spotify, etc.
    foreach (['spotify.com', 'youtube.com', 'instagram.com', 'tiktok.com', 'pinterest.com'] as $domain) {
        if (str_ends_with($bare, '.' . $domain) || $bare === $domain) {
            return $map[$domain] ?? ucfirst(explode('.', $domain)[0]);
        }
    }

    // Fallback: the bare hostname.
    return $bare !== '' ? $bare : 'Open link';
}
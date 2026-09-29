<?php
declare(strict_types=1);

/**
 * Read-only queries for the admin dashboard.
 * Keep these simple and indexed. No caching — the table is tiny.
 */

function admin_stats(): array
{
    $pdo = db();

    $stats = [];

    // Jars: total and active
    $row = $pdo->query(
        'SELECT COUNT(*) AS total,
                SUM(is_active = 1) AS active
         FROM jars'
    )->fetch();
    $stats['jars_total']   = (int)($row['total'] ?? 0);
    $stats['jars_active']  = (int)($row['active'] ?? 0);

    // Messages: total and active
    $row = $pdo->query(
        'SELECT COUNT(*) AS total,
                SUM(is_active = 1) AS active
         FROM messages'
    )->fetch();
    $stats['messages_total']  = (int)($row['total'] ?? 0);
    $stats['messages_active'] = (int)($row['active'] ?? 0);

    // Draws in the last 7 days
    $row = $pdo->query(
        'SELECT COUNT(*) AS n FROM message_views
         WHERE viewed_at > (NOW() - INTERVAL 7 DAY)'
    )->fetch();
    $stats['draws_7d'] = (int)($row['n'] ?? 0);

    // Messages currently locked (unlock_at in the future)
    $row = $pdo->query(
        'SELECT COUNT(*) AS n FROM messages
         WHERE is_active = 1 AND unlock_at IS NOT NULL AND unlock_at > NOW()'
    )->fetch();
    $stats['messages_locked'] = (int)($row['n'] ?? 0);

    // Messages currently expired (is_active but expires_at passed)
    $row = $pdo->query(
        'SELECT COUNT(*) AS n FROM messages
         WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()'
    )->fetch();
    $stats['messages_expired'] = (int)($row['n'] ?? 0);

    return $stats;
}

/**
 * Most recent draws with the message body and jar name.
 * Capped at $limit to keep the page light.
 */
function admin_recent_draws(int $limit = 10): array
{
    $limit = max(1, min(50, $limit)); // clamp, even though it's our own value
    $sql = 'SELECT v.viewed_at,
                   m.id        AS message_id,
                   m.body      AS message_body,
                   j.id        AS jar_id,
                   j.name      AS jar_name,
                   j.emoji     AS jar_emoji,
                   u.username  AS viewer_username
            FROM message_views v
            JOIN messages m ON m.id = v.message_id
            JOIN jars     j ON j.id = m.jar_id
            JOIN users    u ON u.id = v.user_id
            ORDER BY v.viewed_at DESC, v.id DESC
            LIMIT ' . (int)$limit;
    return db()->query($sql)->fetchAll();
}
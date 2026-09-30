<?php
declare(strict_types=1);

/**
 * Data access for messages. Purely server-side: no rendering.
 */

/**
 * Messages belonging to a jar, in creation order.
 * Includes is_active and unlock/expires so the admin list can render badges.
 */
function messages_for_jar(int $jarId): array
{
    $stmt = db()->prepare(
        'SELECT id, jar_id, body, image_url, youtube_id, external_url,
                unlock_at, expires_at, is_active, created_at, updated_at
         FROM messages
         WHERE jar_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([$jarId]);
    return $stmt->fetchAll();
}

function message_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, jar_id, body, image_url, youtube_id, external_url,
                unlock_at, expires_at, is_active, created_at, updated_at
         FROM messages
         WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function message_create(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO messages
            (jar_id, body, image_url, youtube_id, external_url,
             unlock_at, expires_at, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['jar_id'],
        $data['body'],
        $data['image_url'],
        $data['youtube_id'],
        $data['external_url'],
        $data['unlock_at'],
        $data['expires_at'],
        $data['is_active'] ? 1 : 0,
    ]);
    return (int)db()->lastInsertId();
}

function message_update(int $id, array $data): void
{
    $stmt = db()->prepare(
        'UPDATE messages
         SET body = ?, image_url = ?, youtube_id = ?, external_url = ?,
             unlock_at = ?, expires_at = ?, is_active = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['body'],
        $data['image_url'],
        $data['youtube_id'],
        $data['external_url'],
        $data['unlock_at'],
        $data['expires_at'],
        $data['is_active'] ? 1 : 0,
        $id,
    ]);
}

function message_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);
}

function message_toggle(int $id): void
{
    $stmt = db()->prepare('UPDATE messages SET is_active = 1 - is_active WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Validate raw form input for a message.
 * Returns [cleanData, errors].
 *
 * $defaultJarId — used when creating, so a jar_id always exists.
 */
function message_validate(array $in, int $defaultJarId = 0): array
{
    $errors = [];

    // jar_id: if editing, the form supplies it; if creating, use default.
    $jarId = (int)($in['jar_id'] ?? $defaultJarId);
    if ($jarId <= 0 || !jar_find($jarId)) {
        $errors[] = 'Invalid jar.';
    }

    // body
    $body = trim((string)($in['body'] ?? ''));
    if ($body === '') {
        $errors[] = 'Message text is required.';
    } elseif (mb_strlen($body) > 5000) {
        $errors[] = 'Message text must be 5000 characters or fewer.';
    }

    // image_url — must be ImageKit if set, so we never render arbitrary hosts.
    $imageUrl = trim((string)($in['image_url'] ?? ''));
    if ($imageUrl !== '') {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Image URL must be a valid URL.';
        } else {
            $scheme = strtolower((string)parse_url($imageUrl, PHP_URL_SCHEME));
            if ($scheme !== 'https') {
                $errors[] = 'Image URL must use HTTPS.';
            } elseif (!imagekit_url_ok($imageUrl)) {
                $errors[] = 'Image URL must be hosted on your ImageKit endpoint.';
            }
        }
    }

    // youtube_url — accept a URL or an ID. Store only the ID.
    $youtubeIn = trim((string)($in['youtube_url'] ?? ''));
    $youtubeId = null;
    if ($youtubeIn !== '') {
        $youtubeId = youtube_extract_id($youtubeIn);
        if ($youtubeId === null) {
            $errors[] = 'YouTube URL or ID looks invalid.';
        }
    }

    // external_url — must be HTTPS.
    $externalUrl = trim((string)($in['external_url'] ?? ''));
    if ($externalUrl !== '') {
        if (!is_https_url($externalUrl)) {
            $errors[] = 'External link must be a valid HTTPS URL.';
        }
    }

    // unlock/expires: both optional; must be valid dates; expiry must be after unlock.
    $unlockAt  = normalize_datetime((string)($in['unlock_at'] ?? ''),  $errors, 'Unlock date');
    $expiresAt = normalize_datetime((string)($in['expires_at'] ?? ''), $errors, 'Expiry date');

    if ($unlockAt !== null && $expiresAt !== null && $expiresAt <= $unlockAt) {
        $errors[] = 'Expiry date must be after the unlock date.';
    }

    $active = !empty($in['is_active']);

    return [[
        'jar_id'       => $jarId,
        'body'         => $body,
        'image_url'    => $imageUrl !== '' ? $imageUrl : null,
        'youtube_id'   => $youtubeId,
        'external_url' => $externalUrl !== '' ? $externalUrl : null,
        'unlock_at'    => $unlockAt,
        'expires_at'   => $expiresAt,
        'is_active'    => $active,
    ], $errors];
}

/**
 * ImageKit hostnames — kept narrow on purpose.
 * If you migrate to a custom ImageKit domain later, add it here.
 */
function imagekit_host_ok(string $host): bool
{
    return $host === 'ik.imagekit.io';
}

/**
 * Stricter than imagekit_host_ok: the URL must be under YOUR endpoint.
 * Rejects other people's ImageKit URLs and any non-ImageKit host.
 */
function imagekit_url_ok(string $url): bool
{
    $endpoint = (string)(config()['imagekit']['url_endpoint'] ?? '');
    if ($endpoint === '') {
        return false;
    }
    $endpoint = rtrim($endpoint, '/');
    return str_starts_with($url, $endpoint . '/');
}

/**
 * The admin's input is interpreted in the SITE timezone (DISPLAY_TZ,
 * from .env), NOT the visitor's timezone. This is deliberate:
 *
 *   - Display follows the visitor's browser timezone.
 *   - Scheduling (unlock/expires) is anchored to the site's
 *     timezone, so "9 AM" always means "9 AM for the recipient",
 *     regardless of where the admin is editing from.
 *
 * This keeps a message scheduled from a trip abroad unlocking at
 * the intended local hour back home.
 */
function normalize_datetime(string $value, array &$errors, string $label): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat(
            $fmt,
            $value,
            new DateTimeZone(DISPLAY_TZ)
        );
        if ($dt !== false && $dt->format($fmt) === $value) {
            return $dt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }
    }

    $errors[] = $label . ' is not a valid date/time.';
    return null;
}

/**
 * Pick the next message for the given jar and user.
 *
 * Strategy (three tiers, tried in order):
 *
 *   1. NEVER SEEN — messages this user has never viewed in this jar,
 *      oldest first.
 *
 *   2. LEAST RECENTLY SEEN — among messages this user has seen, pick
 *      the one whose last view was the longest ago. Produces a strict
 *      rotation: A, B, C, A, B, C, ... — no repeats until the cycle
 *      completes.
 *
 *   3. NO VIEWS AT ALL — defensive fallback, should be unreachable.
 *
 * Returns the message row, or null if there are no eligible messages.
 */
function draw_message(int $jarId, int $userId): ?array
{
    $pdo = db();

    // 1. Eligible messages, oldest first.
    $stmt = $pdo->prepare(
        'SELECT id, jar_id, body, image_url, youtube_id, external_url,
                unlock_at, expires_at, is_active, created_at
         FROM messages
         WHERE jar_id = ?
           AND is_active = 1
           AND (unlock_at IS NULL OR unlock_at <= NOW())
           AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([$jarId]);
    $eligible = $stmt->fetchAll();

    if (!$eligible) {
        return null;
    }

    // 2. Per-message last-view time for this user, in this jar.
    $stmt = $pdo->prepare(
        'SELECT m.id AS message_id, MAX(v.viewed_at) AS last_seen
         FROM messages m
         LEFT JOIN message_views v
                ON v.message_id = m.id AND v.user_id = ?
         WHERE m.jar_id = ?
         GROUP BY m.id'
    );
    $stmt->execute([$userId, $jarId]);
    $lastSeen = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['last_seen'] !== null) {
            $lastSeen[(int)$row['message_id']] = (string)$row['last_seen'];
        }
    }

    // 3. Pick the next message.
    $chosen = null;

    // Tier 1: never seen, oldest first.
    foreach ($eligible as $m) {
        if (!isset($lastSeen[(int)$m['id']])) {
            $chosen = $m;
            break;
        }
    }

    // Tier 2: least-recently-seen, oldest last-view first.
    // No time cutoff — always rotates through the full set.
    if ($chosen === null) {
        $bestTs = null;
        foreach ($eligible as $m) {
            $ts = strtotime($lastSeen[(int)$m['id']]);
            if ($ts === false) {
                continue;
            }
            if ($bestTs === null || $ts < $bestTs) {
                $bestTs = $ts;
                $chosen = $m;
            }
        }
    }

    // Tier 3: fallback (should be unreachable).
    if ($chosen === null) {
        $chosen = $eligible[0];
    }

    // 4. Record the view.
    $ins = $pdo->prepare(
        'INSERT INTO message_views (user_id, message_id) VALUES (?, ?)'
    );
    $ins->execute([$userId, (int)$chosen['id']]);

    return $chosen;
}

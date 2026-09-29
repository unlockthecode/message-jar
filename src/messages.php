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
            $host   = strtolower((string)parse_url($imageUrl, PHP_URL_HOST));
            if ($scheme !== 'https') {
                $errors[] = 'Image URL must use HTTPS.';
            } elseif (!imagekit_url_ok($imageUrl)) {
                $errors[] = 'Image URL must be hosted on your ImageKit endpoint.';
            }
        }
    }

    // youtube_url — accept a URL or an ID. Store only the ID.
    $youtubeIn  = trim((string)($in['youtube_url'] ?? ''));
    $youtubeId  = null;
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

// Stricter version, if you want it:
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
 * Accept '' (=> null) or a 'Y-m-d\TH:i' string (from <input type="datetime-local">)
 * or a 'Y-m-d H:i:s' string (from older browsers). Anything else is an error.
 *
 * Returns 'Y-m-d H:i:s' or null. Errors accumulate into $errors.
 */
function normalize_datetime(string $value, array &$errors, string $label): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $value);
        if ($dt !== false && $dt->format($fmt) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $errors[] = $label . ' is not a valid date/time.';
    return null;
}

/**
 * Pick a random eligible message for the given jar & user.
 *
 * Returns the message row, or null if none are eligible.
 *
 * Strategy:
 *   1. Fetch eligible message IDs (active, in jar, currently unlocked, not expired).
 *   2. Fetch IDs already viewed by this user for this jar in the last 30 days.
 *   3. Prefer the set difference; fall back to the whole eligible set if empty.
 *   4. Pick one at random, insert a view, return the row.
 */
function draw_message(int $jarId, int $userId): ?array
{
    $pdo = db();

    // 1. Eligible messages
    $stmt = $pdo->prepare(
        'SELECT id FROM messages
         WHERE jar_id = ?
           AND is_active = 1
           AND (unlock_at IS NULL OR unlock_at <= NOW())
           AND (expires_at IS NULL OR expires_at > NOW())'
    );
    $stmt->execute([$jarId]);
    $eligible = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    if (!$eligible) {
        return null;
    }

    // 2. Recently viewed by this user for this jar
    $stmt = $pdo->prepare(
        'SELECT DISTINCT v.message_id
         FROM message_views v
         JOIN messages m ON m.id = v.message_id
         WHERE v.user_id = ?
           AND m.jar_id = ?
           AND v.viewed_at > (NOW() - INTERVAL 30 DAY)'
    );
    $stmt->execute([$userId, $jarId]);
    $recent = array_map('intval', array_column($stmt->fetchAll(), 'message_id'));

    // 3. Prefer unseen
    $unseen = array_values(array_diff($eligible, $recent));
    $pool   = $unseen ?: $eligible;

    // 4. Random pick
    $pickedId = $pool[random_int(0, count($pool) - 1)];

    $stmt = $pdo->prepare(
        'SELECT id, jar_id, body, image_url, youtube_id, external_url,
                unlock_at, expires_at, is_active
         FROM messages WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$pickedId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null; // should never happen, but be defensive
    }

    // 5. Record the view
    $ins = $pdo->prepare(
        'INSERT INTO message_views (user_id, message_id) VALUES (?, ?)'
    );
    $ins->execute([$userId, $pickedId]);

    return $row;
}


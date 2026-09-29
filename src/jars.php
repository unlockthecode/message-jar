<?php
declare(strict_types=1);

/**
 * Data access for jars.
 * Returns plain arrays. No HTML. No session. Just DB + logic.
 */

/**
 * Active jars, ordered for display. Used on the user-facing dashboard.
 */
function jars_active(): array
{
    $stmt = db()->query(
        'SELECT id, name, description, emoji, theme_color, display_order
         FROM jars
         WHERE is_active = 1
         ORDER BY display_order ASC, id ASC'
    );
    return $stmt->fetchAll();
}

/**
 * All jars, with message counts. Used in the admin list.
 * The subquery counts ALL messages, active or not, so the admin sees reality.
 */
function jars_all_with_counts(): array
{
    $sql = 'SELECT j.id, j.name, j.description, j.emoji, j.theme_color,
                   j.is_active, j.display_order, j.created_at, j.updated_at,
                   (SELECT COUNT(*) FROM messages m WHERE m.jar_id = j.id) AS message_count
            FROM jars j
            ORDER BY j.display_order ASC, j.id ASC';
    return db()->query($sql)->fetchAll();
}

/**
 * Single jar by id. Null if not found.
 */
function jar_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, description, emoji, theme_color,
                is_active, display_order, created_at, updated_at
         FROM jars WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Next display_order value for a new jar (appends to the end).
 */
function jar_next_order(): int
{
    $row = db()->query('SELECT COALESCE(MAX(display_order), -1) + 1 AS n FROM jars')->fetch();
    return (int)($row['n'] ?? 0);
}

/**
 * Insert a new jar. Returns the new id.
 */
function jar_create(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO jars (name, description, emoji, theme_color, is_active, display_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['name'],
        $data['description'] ?: null,
        $data['emoji']       ?: null,
        $data['theme_color'] ?: null,
        $data['is_active'] ? 1 : 0,
        $data['display_order'],
    ]);
    return (int)db()->lastInsertId();
}

/**
 * Update an existing jar.
 */
function jar_update(int $id, array $data): void
{
    $stmt = db()->prepare(
        'UPDATE jars
         SET name = ?, description = ?, emoji = ?, theme_color = ?,
             is_active = ?, display_order = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['name'],
        $data['description'] ?: null,
        $data['emoji']       ?: null,
        $data['theme_color'] ?: null,
        $data['is_active'] ? 1 : 0,
        $data['display_order'],
        $id,
    ]);
}

/**
 * Delete a jar. Messages cascade automatically.
 */
function jar_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM jars WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Toggle the is_active flag.
 */
function jar_toggle(int $id): void
{
    $stmt = db()->prepare('UPDATE jars SET is_active = 1 - is_active WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Move a jar up or down one position. Returns true if changed.
 *
 * Implementation note: we swap display_order with the adjacent jar.
 * For fewer than 100 jars, this is far simpler than a linked-list approach.
 */
function jar_move(int $id, string $direction): bool
{
    if (!in_array($direction, ['up', 'down'], true)) {
        return false;
    }

    $pdo = db();

    // Load the target jar
    $stmt = $pdo->prepare('SELECT id, display_order FROM jars WHERE id = ?');
    $stmt->execute([$id]);
    $jar = $stmt->fetch();
    if (!$jar) {
        return false;
    }

    // Find the neighbor in that direction
    if ($direction === 'up') {
        $sql = 'SELECT id, display_order FROM jars
                WHERE display_order < ? OR (display_order = ? AND id < ?)
                ORDER BY display_order DESC, id DESC
                LIMIT 1';
    } else {
        $sql = 'SELECT id, display_order FROM jars
                WHERE display_order > ? OR (display_order = ? AND id > ?)
                ORDER BY display_order ASC, id ASC
                LIMIT 1';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$jar['display_order'], $jar['display_order'], $jar['id']]);
    $neighbor = $stmt->fetch();
    if (!$neighbor) {
        return false; // already at the top/bottom
    }

    // Swap orders inside a transaction
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare('UPDATE jars SET display_order = ? WHERE id = ?');
        $upd->execute([$neighbor['display_order'], $jar['id']]);
        $upd->execute([$jar['display_order'], $neighbor['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return true;
}

/**
 * Validate raw form input for a jar.
 * Returns [cleanData, errors].
 */
function jar_validate(array $in): array
{
    $errors = [];

    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }

    $description = trim((string)($in['description'] ?? ''));
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }

    $emoji = trim((string)($in['emoji'] ?? ''));
    if ($emoji !== '' && mb_strlen($emoji) > 8) {
        // Allow multi-codepoint emoji like 👨‍👩‍👧 (family) but not a novel
        $errors[] = 'Emoji looks too long.';
    }

    $color = trim((string)($in['theme_color'] ?? ''));
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $errors[] = 'Theme color must be a hex value like #ff8fab.';
    }

    $order = (int)($in['display_order'] ?? 0);
    if ($order < 0 || $order > 100000) {
        $errors[] = 'Display order must be a positive number.';
    }

    $active = !empty($in['is_active']);

    return [[
        'name'          => $name,
        'description'   => $description,
        'emoji'         => $emoji,
        'theme_color'   => $color,
        'display_order' => $order,
        'is_active'     => $active,
    ], $errors];
}
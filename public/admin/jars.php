<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user   = require_admin();
$jars   = jars_all_with_counts();
$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Group jars by category. Uncategorized jars go under a synthetic key.
$groups = [];
foreach ($jars as $jar) {
    $cat = trim((string)($jar['category'] ?? ''));
    $key = $cat !== '' ? $cat : '__uncategorized__';
    $groups[$key][] = $jar;
}

// Sort group keys alphabetically, but keep the uncategorized group last.
$groupKeys = array_keys($groups);
usort($groupKeys, function ($a, $b) {
    if ($a === '__uncategorized__') return 1;
    if ($b === '__uncategorized__') return -1;
    return strcasecmp($a, $b);
});

$title  = 'Jars';
$active = 'jars';
require __DIR__ . '/../../templates/header.php';
require __DIR__ . '/../../templates/admin_header.php';
?>
<main class="admin">

    <div class="page-title-row">
        <h1>Jars</h1>
        <div>
            <a href="/admin/jar-edit.php" class="btn">+ New jar</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if (empty($jars)): ?>
        <p class="muted">No jars yet. Create the first one.</p>
    <?php else: ?>
        <?php foreach ($groupKeys as $key): ?>
            <?php
            $isUncat = $key === '__uncategorized__';
            $label   = $isUncat ? 'Uncategorized' : $key;
            // Storage key for localStorage — sanitize by hashing.
            $storeKey = 'jarGroup:' . substr(md5($label), 0, 12);
            ?>
            <section class="jar-group" data-store-key="<?= e($storeKey) ?>">
                <header class="jar-group-header">
                    <button type="button" class="jar-group-toggle"
                            aria-expanded="true">
                        <span class="jar-group-arrow">▾</span>
                        <span class="jar-group-name"><?= e($label) ?></span>
                        <span class="jar-group-count"><?= count($groups[$key]) ?></span>
                    </button>
                </header>

                <div class="jar-group-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Jar</th>
                                <th>Messages</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groups[$key] as $jar): ?>
                            <tr>
                                <td class="order-cell">
                                    <form method="post" action="/admin/jar-reorder.php" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$jar['id'] ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="link" title="Move up">▲</button>
                                    </form>
                                    <span><?= (int)$jar['display_order'] ?></span>
                                    <form method="post" action="/admin/jar-reorder.php" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$jar['id'] ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="link" title="Move down">▼</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="emoji"><?= e($jar['emoji'] ?: '💌') ?></span>
                                    <strong><?= e($jar['name']) ?></strong>
                                    <?php if (!empty($jar['description'])): ?>
                                        <div class="muted small"><?= e($jar['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$jar['message_count'] ?></td>
                                <td>
                                    <?php if ($jar['is_active']): ?>
                                        <span class="badge ok">Active</span>
                                    <?php else: ?>
                                        <span class="badge off">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="/admin/messages.php?jar_id=<?= (int)$jar['id'] ?>">Messages</a>
                                    <a href="/admin/jar-edit.php?id=<?= (int)$jar['id'] ?>">Edit</a>
                                    <form method="post" action="/admin/jar-toggle.php" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$jar['id'] ?>">
                                        <button type="submit" class="link">
                                            <?= $jar['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="/admin/jar-delete.php" class="inline"
                                          data-confirm="Delete &quot;<?= e($jar['name']) ?>&quot;? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$jar['id'] ?>">
                                        <button type="submit" class="link danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
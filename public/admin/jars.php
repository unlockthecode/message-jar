<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user   = require_admin();
$jars   = jars_all_with_counts();
$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

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
            <?php foreach ($jars as $jar): ?>
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
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
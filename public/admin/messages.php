<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user = require_admin();

$jarId = isset($_GET['jar_id']) ? (int)$_GET['jar_id'] : 0;
$jar   = $jarId ? jar_find($jarId) : null;
if (!$jar) {
    abort(404, 'Jar not found.');
}

$messages = messages_for_jar($jarId);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$title  = 'Messages in ' . $jar['name'];
$active = 'jars';
require __DIR__ . '/../../templates/header.php';
require __DIR__ . '/../../templates/admin_header.php';
?>
<main class="admin">

    <div class="page-title-row">
        <h1>
            <span class="emoji"><?= e($jar['emoji'] ?: '💌') ?></span>
            <?= e($jar['name']) ?>
        </h1>
        <div>
            <a href="/admin/jars.php" class="link">Back to jars</a>
            <a href="/admin/message-edit.php?jar_id=<?= (int)$jar['id'] ?>" class="btn">+ New message</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
        <p class="muted">No messages yet. Add the first one.</p>
    <?php else: ?>
        <ul class="message-list">
            <?php foreach ($messages as $m): ?>
                <?php
                  $now = time();
                  $unlockTs  = $m['unlock_at']  ? strtotime((string)$m['unlock_at'])  : null;
                  $expiresTs = $m['expires_at'] ? strtotime((string)$m['expires_at']) : null;
                  $locked    = $unlockTs !== null && $unlockTs > $now;
                  $expired   = $expiresTs !== null && $expiresTs <= $now;
                ?>
                <li class="message-item <?= $m['is_active'] ? '' : 'is-off' ?>">
                    <div class="message-body"><?= e(mb_strimwidth((string)$m['body'], 0, 140, '…')) ?></div>
                    <div class="message-meta">
                        <?php if ($m['is_active']): ?>
                            <span class="badge ok">Active</span>
                        <?php else: ?>
                            <span class="badge off">Inactive</span>
                        <?php endif; ?>

                        <?php if ($locked): ?>
                            <span class="badge">Unlocks <?= e(format_display_time($m['unlock_at'])) ?></span>
                        <?php endif; ?>
                        <?php if ($expired): ?>
                            <span class="badge off">Expired</span>
                        <?php endif; ?>

                        <?php if ($m['image_url'] && imagekit_url_ok((string)$m['image_url'])): ?>
                            <img class="msg-thumb" src="<?= e($m['image_url']) ?>" alt=""
                                 loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <?php endif; ?>
                        <?php if ($m['youtube_id']): ?><span class="badge">video</span><?php endif; ?>
                        <?php if ($m['external_url']): ?><span class="badge">link</span><?php endif; ?>
                    </div>
                    <div class="message-actions">
                        <form method="post" action="/admin/message-toggle.php" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="jar_id" value="<?= (int)$jar['id'] ?>">
                            <button type="submit" class="link">
                                <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <a href="/admin/message-edit.php?id=<?= (int)$m['id'] ?>">Edit</a>
                        <form method="post" action="/admin/message-delete.php" class="inline"
                            data-confirm="Delete this message? This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="jar_id" value="<?= (int)$jar['id'] ?>">
                            <button type="submit" class="link danger">Delete</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
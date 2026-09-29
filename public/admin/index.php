<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user  = require_admin();
$stats = admin_stats();
$draws = admin_recent_draws(10);

$title  = 'Admin dashboard';
$active = 'dashboard';
require __DIR__ . '/../../templates/header.php';
require __DIR__ . '/../../templates/admin_header.php';
?>
<main class="admin">

    <h1>Dashboard</h1>
    <p class="muted">Everything you need to manage the jars at a glance.</p>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['jars_total'] ?></div>
            <div class="stat-label">Jars</div>
            <div class="stat-sub"><?= (int)$stats['jars_active'] ?> active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['messages_total'] ?></div>
            <div class="stat-label">Messages</div>
            <div class="stat-sub"><?= (int)$stats['messages_active'] ?> active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['draws_7d'] ?></div>
            <div class="stat-label">Draws</div>
            <div class="stat-sub">last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['messages_locked'] ?></div>
            <div class="stat-label">Locked</div>
            <div class="stat-sub">waiting to unlock</div>
        </div>
    </section>

    <?php if ($stats['messages_expired'] > 0): ?>
        <div class="alert">
            <strong>Heads up:</strong>
            <?= (int)$stats['messages_expired'] ?>
            active message<?= $stats['messages_expired'] === 1 ? '' : 's' ?>
            have already expired and can never be drawn.
            You may want to deactivate them.
        </div>
    <?php endif; ?>

    <section class="admin-actions">
        <a href="/admin/jar-edit.php" class="btn">+ New jar</a>
        <a href="/admin/jars.php" class="link">Manage jars</a>
        <a href="/dashboard.php" class="link">View as her</a>
    </section>

    <section>
        <h2>Recent draws</h2>
        <?php if (empty($draws)): ?>
            <p class="muted">No draws yet. Once she opens a jar, activity shows up here.</p>
        <?php else: ?>
            <ul class="draw-list">
                <?php foreach ($draws as $d): ?>
                    <li class="draw-item">
                        <div class="draw-when">
                            <?= e(human_time_ago((string)$d['viewed_at'])) ?>
                        </div>
                        <div class="draw-jar">
                            <span class="emoji"><?= e($d['jar_emoji'] ?: '💌') ?></span>
                            <?= e($d['jar_name']) ?>
                        </div>
                        <div class="draw-body">
                            <?= e(mb_strimwidth((string)$d['message_body'], 0, 90, '…')) ?>
                        </div>
                        <div class="draw-who muted small">
                            by <?= e($d['viewer_username']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</main>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
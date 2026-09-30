<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user   = require_login();
$groups = jars_active_grouped();

$title = 'Your jars';
require __DIR__ . '/../templates/header.php';
?>
<main>
    <header class="topbar">
        <nav class="topnav">
            <?php if ($user['role'] === 'admin'): ?>
                <a href="/admin/">Admin</a>
            <?php endif; ?>
            <form method="post" action="/logout.php" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="link">Sign out</button>
            </form>
        </nav>
    </header>

    <section class="greeting">
        <h1>Hi, <?= e($user['username']) ?> 💗</h1>
        <p class="muted">Pick a jar and let me tell you something.</p>
    </section>

    <?php if (empty($groups)): ?>
        <p class="muted" style="text-align:center;">
            <?php if ($user['role'] === 'admin'): ?>
                No jars yet. <a href="/admin/jar-edit.php">Create the first one</a>.
            <?php else: ?>
                No jars yet. Come back soon. ❤️
            <?php endif; ?>
        </p>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <section class="jar-group dashboard-group" data-store-key="<?= e($group['slug']) ?>">
                <header class="jar-group-header">
                    <button type="button" class="jar-group-toggle" aria-expanded="true">
                        <span class="jar-group-arrow">▾</span>
                        <span class="jar-group-name"><?= e($group['label']) ?></span>
                        <span class="jar-group-count"><?= count($group['jars']) ?></span>
                    </button>
                </header>

                <div class="jar-group-body">
                    <div class="jar-grid">
                        <?php foreach ($group['jars'] as $jar): ?>
                            <?php
                              $href = '/jar.php?id=' . (int)$jar['id'];
                              $wrap = 'a';
                              require __DIR__ . '/../templates/jar-card.php';
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
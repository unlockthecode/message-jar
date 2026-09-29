<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$jars = jars_active();

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

    <?php if (empty($jars)): ?>
        <p class="muted" style="text-align:center;">
            <?php if ($user['role'] === 'admin'): ?>
                No jars yet. <a href="/admin/jar-edit.php">Create the first one</a>.
            <?php else: ?>
                No jars yet. Come back soon. ❤️
            <?php endif; ?>
        </p>
    <?php else: ?>
        <section class="jar-grid">
            <?php foreach ($jars as $jar): ?>
                <?php
                  $href = '/jar.php?id=' . (int)$jar['id'];
                  $wrap = 'a';
                  require __DIR__ . '/../templates/jar-card.php';
                ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <script src="/assets/js/jar.js" defer></script>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
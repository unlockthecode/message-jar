<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();

$jarId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$jar   = $jarId ? jar_find($jarId) : null;

if (!$jar || !$jar['is_active']) {
    abort(404, 'Jar not found.');
}

$csrf = csrf_token();

$title = $jar['name'];
require __DIR__ . '/../templates/header.php';
?>
<?php $jarColor = css_hex_color($jar['theme_color'] ?? null); ?>
<main class="jar-page"
      data-jar-id="<?= (int)$jar['id'] ?>"
      data-csrf="<?= e($csrf) ?>"
      data-jar-color="<?= $jarColor ?>">

    <header class="topbar">
        <nav class="topnav">
            <a href="/dashboard.php">← All jars</a>
        </nav>
    </header>

    <h1 class="jar-title">
        <span class="emoji" aria-hidden="true"><?= e($jar['emoji'] ?: '💌') ?></span>
        <?= e($jar['name']) ?>
    </h1>

    <?php if (!empty($jar['description'])): ?>
        <p class="muted jar-desc"><?= e($jar['description']) ?></p>
    <?php endif; ?>

    <div class="jar-stage" id="jar-stage">
        <button type="button" class="jar-big" id="jar-btn"
                aria-label="Open this jar">
            <span class="jar-big-lid" aria-hidden="true"></span>
            <span class="jar-big-body" aria-hidden="true">
                <span class="jar-big-emoji"><?= e($jar['emoji'] ?: '💌') ?></span>
            </span>
            <span class="jar-big-hint">tap to open</span>
        </button>
    </div>
        
    <div id="msg-slot" class="msg-slot" hidden aria-live="polite"></div>
        
    <div class="jar-actions" id="jar-actions" hidden>
        <button id="draw-btn" type="button" class="btn">Draw another</button>
    </div>
    <script>
        // Apply jar color via JS to avoid an inline-style resolution
        // quirk where the browser ignores the custom property before
        // the stylesheet is fully parsed.
        (function () {
            var page = document.querySelector('.jar-page');
            if (page && page.dataset.jarColor) {
                page.style.setProperty('--jar-color', page.dataset.jarColor);
            }
        })();
    </script>
</main>

<script src="/assets/js/jar.js" defer></script>
<?php require __DIR__ . '/../templates/footer.php'; ?>
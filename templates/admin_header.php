<?php
declare(strict_types=1);
/**
 * Expects:
 *   $title — page title (already escaped by caller? no — we escape here)
 *   $user  — the logged-in user array (from require_admin())
 *   $active — optional: 'dashboard' | 'jars' | 'messages' for highlighting
 */
$active = $active ?? '';
?>
<header class="admin-topbar">
    <div class="admin-brand">
        <a href="/admin/">Admin</a>
    </div>

    <nav class="admin-nav" aria-label="Admin navigation">
        <a href="/admin/" class="<?= $active === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
        <a href="/admin/jars.php" class="<?= $active === 'jars' ? 'is-active' : '' ?>">Jars</a>
        <a href="/dashboard.php" title="Back to the user-facing site">View site</a>
    </nav>

    <form method="post" action="/logout.php" class="inline admin-signout">
        <?= csrf_field() ?>
        <button type="submit" class="link">Sign out</button>
    </form>
</header>
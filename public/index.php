<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// This page's only job: send people where they belong.
if (current_user() === null) {
    redirect('/login.php');
}
redirect('/dashboard.php');

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Message Jar — Phase 1</title>
<style>
    body { font-family: system-ui, sans-serif; background:#fff5f7; color:#3a2a30; padding:2rem; max-width:680px; margin:auto; }
    h1 { color:#c9184a; }
    .card { background:#fff; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 8px 24px rgba(201,24,74,.08); margin-bottom:1rem; }
    .row { display:flex; justify-content:space-between; padding:.35rem 0; border-bottom:1px solid #f2e1e6; }
    .row:last-child { border-bottom:none; }
    .ok  { color:#177245; font-weight:600; }
    .bad { color:#a4133c; font-weight:600; }
    code { background:#f7e6eb; padding:.1rem .35rem; border-radius:4px; }
</style>
</head>
<body>
<h1>Message Jar — Phase 1</h1>

<div class="card">
    <div class="row"><span>PHP version</span><span class="ok"><?= htmlspecialchars(PHP_VERSION) ?></span></div>
    <div class="row"><span>App environment</span><span><?= htmlspecialchars($cfg['app']['env']) ?></span></div>
    <div class="row"><span>App debug</span><span><?= $cfg['app']['debug'] ? 'on' : 'off' ?></span></div>
    <div class="row"><span>Session name</span><span><?= htmlspecialchars($cfg['session']['name']) ?></span></div>
    <div class="row">
        <span>Database</span>
        <?php if ($dbOk): ?>
            <span class="ok">connected (MySQL <?= htmlspecialchars((string)$version) ?>)</span>
        <?php else: ?>
            <span class="bad">not connected — <?= htmlspecialchars((string)$dbError) ?></span>
        <?php endif; ?>
    </div>
</div>

<p>If everything above is green, your skeleton is ready. Next phase: database schema.</p>
</body>
</html>
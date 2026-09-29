<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();
if (!is_post()) abort(405);
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0 && jar_find($id)) {
    jar_toggle($id);
    $_SESSION['flash'] = 'Jar updated.';
}

redirect('/admin/jars.php');
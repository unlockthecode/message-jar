<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();
if (!is_post()) abort(405);
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$direction = (string)($_POST['direction'] ?? '');

if ($id > 0) {
    jar_move($id, $direction);
}

redirect('/admin/jars.php');
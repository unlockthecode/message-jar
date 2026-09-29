<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();
if (!is_post()) abort(405);
csrf_verify();

$id    = (int)($_POST['id'] ?? 0);
$jarId = (int)($_POST['jar_id'] ?? 0);

if ($id > 0 && message_find($id)) {
    message_toggle($id);
    $_SESSION['flash'] = 'Message updated.';
}

redirect('/admin/messages.php?jar_id=' . $jarId);
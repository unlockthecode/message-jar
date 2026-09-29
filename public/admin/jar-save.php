<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

if (!is_post()) {
    abort(405, 'Method not allowed.');
}

csrf_verify();

$id   = (int)($_POST['id'] ?? 0);
$isNew = $id === 0;

[$data, $errors] = jar_validate($_POST);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = $data + ['id' => $id];
    redirect($isNew ? '/admin/jar-edit.php' : '/admin/jar-edit.php?id=' . $id);
}

if ($isNew) {
    $newId = jar_create($data);
    $_SESSION['flash'] = 'Jar created.';
} else {
    if (!jar_find($id)) {
        abort(404, 'Jar not found.');
    }
    jar_update($id, $data);
    $_SESSION['flash'] = 'Jar updated.';
}

redirect('/admin/jars.php');
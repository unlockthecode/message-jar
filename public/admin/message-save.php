<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();
if (!is_post()) abort(405);
csrf_verify();

$id    = (int)($_POST['id'] ?? 0);
$jarId = (int)($_POST['jar_id'] ?? 0);
$isNew = $id === 0;

// Confirm the jar exists before doing anything.
$jar = $jarId ? jar_find($jarId) : null;
if (!$jar) {
    abort(404, 'Jar not found.');
}

[$data, $errors] = message_validate($_POST, $jarId);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = [
        'jar_id'       => $jarId,
        'body'         => (string)($_POST['body'] ?? ''),
        'image_url'    => (string)($_POST['image_url'] ?? ''),
        'youtube_id'   => (string)($_POST['youtube_url'] ?? ''),
        'external_url' => (string)($_POST['external_url'] ?? ''),
        'unlock_at'    => (string)($_POST['unlock_at'] ?? ''),
        'expires_at'   => (string)($_POST['expires_at'] ?? ''),
        'is_active'    => !empty($_POST['is_active']) ? 1 : 0,
    ];
    redirect($isNew
        ? '/admin/message-edit.php?jar_id=' . $jarId
        : '/admin/message-edit.php?id=' . $id);
}

if ($isNew) {
    message_create($data);
    $_SESSION['flash'] = 'Message added.';
} else {
    if (!message_find($id)) {
        abort(404, 'Message not found.');
    }
    message_update($id, $data);
    $_SESSION['flash'] = 'Message updated.';
}

redirect('/admin/messages.php?jar_id=' . $jarId);
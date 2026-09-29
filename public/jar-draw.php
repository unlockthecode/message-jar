<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Require login. Return JSON, not a redirect, so the client can show an error.
$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// CSRF: accept token via POST field or X-CSRF-Token header.
$sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$have = $_SESSION['_csrf'] ?? '';
if (!is_string($sent) || $sent === '' || !hash_equals((string)$have, $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$jarId = isset($_POST['jar_id']) ? (int)$_POST['jar_id'] : 0;
$jar   = $jarId ? jar_find($jarId) : null;
if (!$jar || !$jar['is_active']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'jar_not_found']);
    exit;
}

$msg = draw_message($jarId, (int)$user['id']);

if ($msg === null) {
    echo json_encode([
        'ok'      => true,
        'empty'   => true,
        'message' => "Looks like this jar is empty for now. I'll put something here soon. ❤️",
    ]);
    exit;
}

echo json_encode([
    'ok'   => true,
    'html' => render_message_card($msg),
]);
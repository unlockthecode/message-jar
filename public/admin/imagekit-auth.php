<?php
declare(strict_types=1);

/**
 * ImageKit client-side upload — authentication endpoint.
 *
 * The browser cannot upload to ImageKit without a short-lived
 * "signature" that only the server (with the private key) can
 * generate. This endpoint generates one per request and returns
 * it to the admin's browser.
 *
 * Security:
 *   - require_admin()   : only logged-in admins can request credentials
 *   - csrf_verify()     : only our own admin pages can call this
 *   - POST only         : nothing happens on a GET
 *   - JSON response     : no HTML leaking
 *   - never echoes the private key
 */

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

if (!is_post()) {
    abort(405, 'Method not allowed.');
}

csrf_verify();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $params = imagekit_auth_params();

    echo json_encode([
        'ok'        => true,
        'token'     => $params['token'],
        'expire'    => $params['expire'],
        'signature' => $params['signature'],
        'publicKey' => (string)(config()['imagekit']['public_key'] ?? ''),
    ]);
} catch (Throwable $e) {
    // Never leak the private key or a stack trace.
    error_log('imagekit-auth error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'ImageKit is not configured. Check IMAGEKIT_PRIVATE_KEY.',
    ]);
}
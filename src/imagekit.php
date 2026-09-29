<?php
declare(strict_types=1);

/**
 * ImageKit client-side upload authentication.
 *
 * ImageKit's client-side upload flow requires the browser to send
 * three values alongside the file: token, expire, and signature.
 * The signature is an HMAC-SHA1 of "token + expire" using your
 * ImageKit PRIVATE key as the secret.
 *
 * Docs: https://imagekit.io/docs/api-reference/upload-file/upload-file
 */

/**
 * Generate the auth parameters for a client-side upload.
 *
 * Returns ['token' => '...', 'expire' => 1234567890, 'signature' => '...'].
 */
function imagekit_auth_params(): array
{
    $privateKey = (string)(config()['imagekit']['private_key'] ?? '');
    if ($privateKey === '') {
        throw new RuntimeException('IMAGEKIT_PRIVATE_KEY is not configured.');
    }

    // Random one-time token. ImageKit rejects reuse, so we don't
    // cache or persist these.
    $token  = bin2hex(random_bytes(16));

    // Expiry 30 minutes from now. Must be less than 1 hour into the future.
    $expire = time() + 1800;

    // HMAC-SHA1 signature, lowercase hex.
    $signature = hash_hmac('sha1', $token . $expire, $privateKey);

    return [
        'token'     => $token,
        'expire'    => $expire,
        'signature' => $signature,
    ];
}
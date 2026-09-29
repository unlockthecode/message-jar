<?php
declare(strict_types=1);

/**
 * CSRF protection.
 *
 * One token per session, regenerated on login.
 * Emitted in forms as a hidden input, verified on every POST.
 *
 * Why one token per session instead of per-form?
 *   - Simpler.
 *   - Still safe: the token is unpredictable to an attacker
 *     who can't read the DOM or the session cookie.
 *   - If you want per-form tokens later, swap csrf_token() to
 *     generate one per action and store them in $_SESSION['csrf'][$action].
 */

const CSRF_FIELD = '_csrf';

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_FIELD])) {
        $_SESSION[CSRF_FIELD] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_FIELD];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_FIELD . '" value="'
        . e(csrf_token()) . '">';
}

/**
 * Verify the token on a POST request. Dies with 403 if invalid.
 */
function csrf_verify(): void
{
    if (!is_post()) {
        return; // nothing to verify
    }

    $sent = $_POST[CSRF_FIELD] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $have = $_SESSION[CSRF_FIELD] ?? '';

    if (!is_string($sent) || $sent === '' || $have === '' || !hash_equals($have, $sent)) {
        abort(403, 'Invalid CSRF token.');
    }
}

/**
 * Rotate the token. Called after login so a token that leaked
 * pre-auth can't be reused post-auth.
 */
function csrf_rotate(): void
{
    unset($_SESSION[CSRF_FIELD]);
}
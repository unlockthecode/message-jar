<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Logout must be POST to prevent CSRF-triggered logouts from a third-party page.
if (is_post()) {
    csrf_verify();
    logout_user();
}

redirect('/login.php');
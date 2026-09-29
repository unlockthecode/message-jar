<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Already logged in? Send home.
if (current_user() !== null) {
    redirect('/dashboard.php');
}

$error = null;
$username = '';

if (is_post()) {
    csrf_verify();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both a username and password.';
    } else {
        [$ok, $reason] = attempt_login($username, $password);

        if ($ok) {
            redirect('/dashboard.php');
        }

        if ($reason === 'too_many_attempts') {
            $error = 'Too many attempts. Please wait about 15 minutes and try again.';
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$title = 'Sign in';
require __DIR__ . '/../templates/header.php';
?>
<main class="auth-page">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to open your jars.</p>

    <?php if ($error !== null): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php" autocomplete="on">
        <?= csrf_field() ?>

        <label>
            Username
            <input type="text" name="username" required
                   value="<?= e($username) ?>" autocomplete="username">
        </label>

        <label>
            Password
            <input type="password" name="password" required
                   autocomplete="current-password">
        </label>

        <button type="submit">Sign in</button>
    </form>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
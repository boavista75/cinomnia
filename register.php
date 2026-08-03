<?php

declare(strict_types=1);

/**
 * Cinomnia Registration Page
 *
 * Creates new user accounts with server-side validation,
 * bcrypt/argon2 password hashing, and CSRF protection.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

if ($auth->isLoggedIn()) {
    redirect('/index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken(postParam('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = $auth->register(
            postParam('username'),
            postParam('email'),
            postParam('password'),
            postParam('confirm_password')
        );

        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Register';

require __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">
        <h1 class="auth-card__title">Create Account</h1>
        <p class="auth-card__subtitle">Join <?= Security::escape(APP_NAME) ?> today</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= Security::escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert">
                <?= Security::escape($success) ?>
                <a href="<?= Security::escape(BASE_URL) ?>/login.php">Sign in now &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if ($success === ''): ?>
            <form class="auth-form" method="POST" action="" novalidate>
                <?= Security::csrfField() ?>

                <div class="form-group">
                    <label for="username" class="form-group__label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-group__input"
                        value="<?= Security::escape(postParam('username')) ?>"
                        required
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]{3,50}"
                        autocomplete="username"
                        autofocus
                    >
                    <small class="form-group__hint">3–50 characters, letters, numbers, underscore</small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-group__label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-group__input"
                        value="<?= Security::escape(postParam('email')) ?>"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-group__label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-group__input"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <small class="form-group__hint">Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-group__label">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-group__input"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn--primary btn--full">Create Account</button>
            </form>
        <?php endif; ?>

        <p class="auth-card__footer">
            Already have an account?
            <a href="<?= Security::escape(BASE_URL) ?>/login.php">Sign in here</a>
        </p>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

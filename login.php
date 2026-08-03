<?php

declare(strict_types=1);

/**
 * Cinomnia Login Page
 *
 * Authenticates users via username/email + password.
 * Creates a secure session on success with CSRF protection on the form.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

// Redirect already-authenticated users to home
if ($auth->isLoggedIn()) {
    redirect('/index.php');
}

$error    = '';
$success  = '';
$redirect = safeRedirectPath(getParam('redirect'));

if (getParam('message') !== '') {
    $success = getParam('message');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken(postParam('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $identifier = postParam('identifier');
        $password   = postParam('password');

        try {
            $result = $auth->login($identifier, $password);

            if ($result['success']) {
                redirect($redirect);
            }

            $error = $result['message'];
        } catch (Throwable) {
            $error = 'Something went wrong while signing you in. Please try again.';
        }
    }
}

$pageTitle = 'Login';

require __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">
        <h1 class="auth-card__title">Sign In</h1>
        <p class="auth-card__subtitle">Welcome back to <?= Security::escape(APP_NAME) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= Security::escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert"><?= Security::escape($success) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="" novalidate>
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label for="identifier" class="form-group__label">Username or Email</label>
                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    class="form-group__input"
                    value="<?= Security::escape(postParam('identifier')) ?>"
                    required
                    autocomplete="username"
                    autofocus
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
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn--primary btn--full">Sign In</button>
        </form>

        <p class="auth-card__footer">
            Don't have an account?
            <a href="<?= Security::escape(BASE_URL) ?>/register.php">Register here</a>
        </p>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

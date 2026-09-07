<?php

declare(strict_types=1);

/**
 * Cinomnia Login Page
 *
 * Authenticates the single owner account. Registration is disabled.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

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
        try {
            $result = $auth->login(postParam('username'), postParam('password'));

            if ($result['success']) {
                redirect($redirect);
            }

            $error = $result['message'];
        } catch (Throwable) {
            $error = 'Something went wrong while signing you in. Please try again.';
        }
    }
}

$pageTitle = 'Sign In';

require __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">
        <p class="auth-card__mark" aria-hidden="true">C</p>
        <h1 class="auth-card__title">Welcome back</h1>
        <p class="auth-card__subtitle">Private cinema library — <?= Security::escape(APP_NAME) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= Security::escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert"><?= Security::escape($success) ?></div>
        <?php endif; ?>

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
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

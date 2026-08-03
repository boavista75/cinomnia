<?php

declare(strict_types=1);

/**
 * Site navigation — premium, responsive, auth-aware component.
 *
 * Requires bootstrap ($auth) to be loaded before inclusion.
 */

use Cinomnia\Security\Security;

$currentPage   = basename($_SERVER['PHP_SELF'] ?? '');
$isLoggedIn    = $auth->isLoggedIn();
$username      = $isLoggedIn ? $auth->getUsername() : '';
$isAdmin       = $isLoggedIn && $admin->isAdmin((int) $auth->getUserId());
$avatarInitial = $username !== '' ? strtoupper(substr($username, 0, 1)) : '';

$homeHref     = Security::escape(BASE_URL) . '/index.php';
$loginHref    = Security::escape(BASE_URL) . '/login.php';
$registerHref = Security::escape(BASE_URL) . '/register.php';
$listsHref    = Security::escape(BASE_URL) . '/lists.php';
$adminHref    = Security::escape(BASE_URL) . '/admin.php';
$logoutHref   = Security::escape(BASE_URL) . '/logout.php';

$linkClass = static function (string $page, string $extra = '') use ($currentPage): string {
    $classes = ['site-nav__link'];

    if ($currentPage === $page) {
        $classes[] = 'site-nav__link--active';
    }

    if ($extra !== '') {
        $classes[] = $extra;
    }

    return implode(' ', $classes);
};
?>
<link rel="stylesheet" href="<?= Security::escape(BASE_URL) ?>/css/navbar.css">

<header class="site-nav" id="site-navbar">
    <div class="site-nav__container">
        <a href="<?= $homeHref ?>" class="site-nav__brand" aria-label="<?= Security::escape(APP_NAME) ?> home">
            <span class="site-nav__mark" aria-hidden="true">C</span>
            <span class="site-nav__wordmark"><?= Security::escape(APP_NAME) ?></span>
        </a>

        <button type="button"
                class="site-nav__toggle"
                id="navbar-toggle"
                aria-expanded="false"
                aria-controls="navbar-menu"
                aria-label="Open navigation menu">
            <span class="site-nav__toggle-icon" aria-hidden="true">
                <span class="site-nav__toggle-line"></span>
                <span class="site-nav__toggle-line"></span>
                <span class="site-nav__toggle-line"></span>
            </span>
        </button>

        <div class="site-nav__panel" id="navbar-menu">
            <ul class="site-nav__group site-nav__group--main">
                <li>
                    <a href="<?= $homeHref ?>"
                       class="<?= Security::escape($linkClass('index.php')) ?>"
                       <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                        Home
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <li>
                        <a href="<?= $listsHref ?>"
                           class="<?= Security::escape($linkClass('lists.php')) ?>"
                           <?= $currentPage === 'lists.php' ? 'aria-current="page"' : '' ?>>
                            My Lists
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                        <li>
                            <a href="<?= $adminHref ?>"
                               class="<?= Security::escape($linkClass('admin.php')) ?>"
                               <?= $currentPage === 'admin.php' ? 'aria-current="page"' : '' ?>>
                                Admin
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="site-nav__group site-nav__group--account">
                <?php if ($isLoggedIn): ?>
                    <li>
                        <a href="<?= $listsHref ?>"
                           class="site-nav__profile<?= $currentPage === 'lists.php' ? ' site-nav__profile--active' : '' ?>"
                           aria-label="Profile: <?= Security::escape($username) ?>">
                            <span class="site-nav__avatar" aria-hidden="true"><?= Security::escape($avatarInitial) ?></span>
                            <span class="site-nav__username"><?= Security::escape($username) ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $logoutHref ?>" class="site-nav__link site-nav__link--logout">Logout</a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?= $loginHref ?>"
                           class="<?= Security::escape($linkClass('login.php')) ?>"
                           <?= $currentPage === 'login.php' ? 'aria-current="page"' : '' ?>>
                            Login
                        </a>
                    </li>
                    <li>
                        <a href="<?= $registerHref ?>"
                           class="<?= Security::escape($linkClass('register.php', 'site-nav__link--register')) ?>"
                           <?= $currentPage === 'register.php' ? 'aria-current="page"' : '' ?>>
                            Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="site-nav__backdrop" id="navbar-backdrop" hidden aria-hidden="true"></div>
</header>

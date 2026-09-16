<?php

declare(strict_types=1);

/**
 * Site navigation — private single-user app.
 *
 * Rendered as a floating glass dock that hovers above the page content.
 * Requires bootstrap.php to be loaded before inclusion.
 */

use Cinomnia\Security\Security;

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$homeHref  = Security::escape(BASE_URL) . '/index.php';
$listsHref = Security::escape(BASE_URL) . '/lists.php';

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
<link rel="stylesheet" href="<?= Security::escape(BASE_URL) ?>/css/navbar.css?v=<?= (int) filemtime(APP_ROOT . '/css/navbar.css') ?>">

<header class="site-nav" id="site-navbar">
    <div class="site-nav__dock">
        <a href="<?= $homeHref ?>" class="site-nav__brand" aria-label="<?= Security::escape(APP_NAME) ?> home">
            <span class="site-nav__mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/>
                    <path fill="currentColor" d="M10.2 8.4v7.2l6-3.6-6-3.6z"/>
                </svg>
            </span>
            <span class="site-nav__wordmark"><?= Security::escape(APP_NAME) ?></span>
        </a>

        <div class="site-nav__panel" id="navbar-menu">
            <ul class="site-nav__group site-nav__group--main">
                <li>
                    <a href="<?= $homeHref ?>"
                       class="<?= Security::escape($linkClass('index.php')) ?>"
                       <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?= $listsHref ?>"
                       class="<?= Security::escape($linkClass('lists.php')) ?>"
                       <?= $currentPage === 'lists.php' ? 'aria-current="page"' : '' ?>>
                        My Lists
                    </a>
                </li>
            </ul>
        </div>

        <div class="site-nav__actions">
            <button type="button"
                    class="theme-switch"
                    id="theme-toggle"
                    role="switch"
                    aria-checked="true"
                    aria-label="Switch to light theme">
                <span class="theme-switch__thumb" aria-hidden="true"></span>
                <svg class="theme-switch__icon theme-switch__icon--moon" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M15.2 2.1a.8.8 0 0 1 .9 1.1 8.6 8.6 0 1 0 4.7 4.7.8.8 0 0 1 1.1.9A10.2 10.2 0 1 1 15.2 2.1z"/>
                </svg>
                <svg class="theme-switch__icon theme-switch__icon--sun" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M12 7.2a4.8 4.8 0 1 1 0 9.6 4.8 4.8 0 0 1 0-9.6zm0-5.2a.9.9 0 0 1 .9.9v1.4a.9.9 0 1 1-1.8 0V2.9A.9.9 0 0 1 12 2zm0 16.4a.9.9 0 0 1 .9.9v1.4a.9.9 0 1 1-1.8 0v-1.4a.9.9 0 0 1 .9-.9zm10-7.4a.9.9 0 0 1-.9.9h-1.4a.9.9 0 1 1 0-1.8H21a.9.9 0 0 1 .9.9zM5.3 12a.9.9 0 0 1-.9.9H3a.9.9 0 1 1 0-1.8h1.4a.9.9 0 0 1 .9.9zm13.4-6.7a.9.9 0 0 1 0 1.3l-1 1a.9.9 0 1 1-1.3-1.3l1-1a.9.9 0 0 1 1.3 0zM7.6 16.4a.9.9 0 0 1 0 1.3l-1 1a.9.9 0 1 1-1.3-1.3l1-1a.9.9 0 0 1 1.3 0zm11.5 1.3a.9.9 0 0 1-1.3 0l-1-1a.9.9 0 1 1 1.3-1.3l1 1a.9.9 0 0 1 0 1.3zM7.6 7.6a.9.9 0 0 1-1.3 0l-1-1A.9.9 0 0 1 6.6 5.3l1 1a.9.9 0 0 1 0 1.3z"/>
                </svg>
            </button>

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
        </div>
    </div>

    <div class="site-nav__backdrop" id="navbar-backdrop" hidden aria-hidden="true"></div>
</header>

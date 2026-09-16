<?php

declare(strict_types=1);

/**
 * Shared HTML head, opening body tag, and site navbar.
 *
 * Expects $pageTitle (string) to be set before inclusion.
 * Requires bootstrap.php to be loaded first.
 */

use Cinomnia\Security\Security;

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Cinomnia — Discover movies and TV shows powered by TMDB">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <title><?= Security::escape($pageTitle) ?> | <?= Security::escape(APP_NAME) ?></title>
    <script>
        (function () {
            var key = 'cinomnia-theme';
            var theme = 'dark';
            try {
                var stored = localStorage.getItem(key);
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                } else if (window.matchMedia('(prefers-color-scheme: light)').matches) {
                    theme = 'light';
                }
            } catch (e) {}
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Inter only stands in for San Francisco where SF is unavailable (Linux/Windows). -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= Security::escape(BASE_URL) ?>/css/style.css?v=<?= (int) filemtime(APP_ROOT . '/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='26' fill='%23000000'/><text x='50' y='70' text-anchor='middle' fill='%230A84FF' font-family='-apple-system,system-ui,sans-serif' font-size='58' font-weight='600'>C</text></svg>">
    <style>
        html:not(.app-ready){overflow:hidden}
        html{background:#000}
        html[data-theme="light"]{background:#fff}
        .app-loader{position:fixed;inset:0;z-index:5000;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:var(--loader-bg,rgba(0,0,0,.84));-webkit-backdrop-filter:saturate(180%) blur(30px);backdrop-filter:saturate(180%) blur(30px);transition:opacity .36s cubic-bezier(.32,.72,0,1),visibility .36s cubic-bezier(.32,.72,0,1)}
        html.app-ready .app-loader{opacity:0;visibility:hidden;pointer-events:none}
        .app-loader__card{display:flex;flex-direction:column;align-items:center;gap:1rem;max-width:20rem;text-align:center;color:var(--loader-fg,#F5F5F7);font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','Inter','Segoe UI',Roboto,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        .app-loader__brand{margin:0;font-size:1.7rem;font-weight:600;line-height:1.1;letter-spacing:-.03em}
        .app-loader__spinner{width:2rem;height:2rem;border:2px solid rgba(245,245,247,.14);border-top-color:var(--accent,#0A84FF);border-radius:50%;animation:appLoaderSpin .8s linear infinite}
        html[data-theme="light"] .app-loader{background:var(--loader-bg,rgba(255,255,255,.86))}
        html[data-theme="light"] .app-loader__card{color:var(--loader-fg,#1D1D1F)}
        html[data-theme="light"] .app-loader__spinner{border:2px solid rgba(29,29,31,.12);border-top-color:var(--accent,#0071E3)}
        .app-loader__title{margin:0;font-size:1.02rem;font-weight:600;line-height:1.3;letter-spacing:-.01em}
        .app-loader__text{margin:0;font-size:.9rem;font-weight:400;line-height:1.45;color:var(--loader-muted,rgba(245,245,247,.58))}
        @keyframes appLoaderSpin{to{transform:rotate(360deg)}}
        @media (prefers-reduced-motion:reduce){.app-loader__spinner{animation:none;border-top-color:currentColor}}
    </style>
</head>
<body>
<div id="app-loader" class="app-loader" role="status" aria-live="polite" aria-busy="true">
    <div class="app-loader__card">
        <p class="app-loader__brand"><?= Security::escape(APP_NAME) ?></p>
        <span class="app-loader__spinner" aria-hidden="true"></span>
        <p class="app-loader__title">Please wait</p>
        <p class="app-loader__text" id="app-loader-text">Loading… this may take a moment.</p>
    </div>
</div>
<?php require __DIR__ . '/navbar.php'; ?>
<?php
if (function_exists('cinomniaFlush')) {
    cinomniaFlush();
}

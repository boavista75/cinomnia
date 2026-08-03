<?php

declare(strict_types=1);

/**
 * Shared HTML head, opening body tag, and site navbar.
 *
 * Expects $pageTitle (string) to be set before inclusion.
 * Requires bootstrap.php (provides $auth) to be loaded first.
 */

use Cinomnia\Security\Security;

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cinomnia — Discover movies and TV shows powered by TMDB">
    <title><?= Security::escape($pageTitle) ?> | <?= Security::escape(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="<?= Security::escape(BASE_URL) ?>/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23326273'/><text x='50' y='68' text-anchor='middle' fill='%23FFFFFF' font-family='sans-serif' font-size='52' font-weight='700'>C</text></svg>">
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>

<?php

declare(strict_types=1);

/**
 * Cinomnia Bootstrap
 *
 * Entry point for all PHP pages. Loads configuration, registers autoloading,
 * initialises secure sessions, and exposes shared service instances.
 */

define('CINOMNIA_APP', true);

require_once __DIR__ . '/../config/config.php';

// Simple PSR-4-style autoloader for Cinomnia\ namespace
spl_autoload_register(static function (string $class): void {
    $prefix = 'Cinomnia\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = APP_ROOT . '/src/' . $relative . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Cinomnia\Auth\AdminService;
use Cinomnia\Auth\AuthService;
use Cinomnia\Auth\CommentService;
use Cinomnia\Auth\CustomListService;
use Cinomnia\Auth\UserRatingsHistoryService;
use Cinomnia\Security\Security;
use Cinomnia\Services\TMDB_Service;

// Initialise hardened session before any output
Security::initSession();

/** Shared service instances available to all pages */
$tmdb        = new TMDB_Service();
$auth        = new AuthService();
$customLists = new CustomListService();
$userMedia   = new UserRatingsHistoryService($customLists);
$customLists->setRatingsHistoryService($userMedia);
$comments    = new CommentService();
$admin       = new AdminService();

/**
 * Helper: redirect and exit.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Validate a post-login redirect path (prevent open redirects).
 */
function safeRedirectPath(?string $path): string
{
    if ($path === null || $path === '') {
        return '/index.php';
    }

    if (!str_starts_with($path, '/') || str_contains($path, '://')) {
        return '/index.php';
    }

    return $path;
}

/**
 * Helper: get a sanitized GET parameter.
 */
function getParam(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/**
 * Helper: get a sanitized POST parameter.
 */
function postParam(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/**
 * Build a URL query string for browse filters, omitting empty defaults.
 *
 * @param array<string, scalar|null> $params
 */
function buildFilterQuery(array $params): string
{
    $allowed = ['type', 'sort', 'genre', 'year_from', 'year_to', 'rating', 'q', 'page'];
    $filtered = [];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $params)) {
            continue;
        }

        $value = $params[$key];

        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            continue;
        }

        $filtered[$key] = $value;
    }

    return http_build_query($filtered);
}

/**
 * Validate and normalise browse filter values from GET parameters.
 *
 * @return array{
 *     sort: string,
 *     genre_id: int,
 *     year_from: int,
 *     year_to: int,
 *     rating: string
 * }
 */
function parseBrowseFilters(): array
{
    $sort = getParam('sort', 'trending');
    $sort = in_array($sort, ['trending', 'highest', 'lowest'], true) ? $sort : 'trending';

    $rating = getParam('rating', '');
    $allowedRatings = ['below5', 'above5', 'above6', 'above7', 'above8', 'above9'];
    $rating = in_array($rating, $allowedRatings, true) ? $rating : '';

    $currentYear = (int) date('Y');
    $yearFrom    = max(0, (int) getParam('year_from', '0'));
    $yearTo      = max(0, (int) getParam('year_to', '0'));

    if ($yearFrom > 0 && ($yearFrom < 1900 || $yearFrom > $currentYear + 1)) {
        $yearFrom = 0;
    }
    if ($yearTo > 0 && ($yearTo < 1900 || $yearTo > $currentYear + 1)) {
        $yearTo = 0;
    }

    return [
        'sort'       => $sort,
        'genre_id'   => max(0, (int) getParam('genre', '0')),
        'year_from'  => $yearFrom,
        'year_to'    => $yearTo,
        'rating'     => $rating,
    ];
}

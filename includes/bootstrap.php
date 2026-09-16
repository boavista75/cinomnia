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

// Prefer HTTPS responses (HSTS) when the request is already secure.
if (defined('APP_IS_HTTPS') && APP_IS_HTTPS && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (!headers_sent()) {
    header('X-Accel-Buffering: no');
}

/**
 * Push already-rendered HTML to the browser so a loading screen can appear
 * before slow TMDB work finishes.
 */
function cinomniaFlush(): void
{
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    flush();
}

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

use Cinomnia\Auth\CustomListService;
use Cinomnia\Auth\NoteService;
use Cinomnia\Auth\UserRatingsHistoryService;
use Cinomnia\Security\Security;
use Cinomnia\Services\TMDB_Service;
use Cinomnia\Storage\JsonStore;

// Initialise hardened session before any output (CSRF tokens)
Security::initSession();

/** Shared service instances available to all pages */
$store       = new JsonStore();
$tmdb        = new TMDB_Service();
$customLists = new CustomListService($store);
$userMedia   = new UserRatingsHistoryService($store, $customLists);
$customLists->setRatingsHistoryService($userMedia);
$notes       = new NoteService($store);

/**
 * Helper: redirect and exit.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
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

/**
 * @param array<string, array{rating: ?int, is_watched: bool, want_to_watch: bool, currently_watching: bool}> $index
 * @return array{rating: ?int, is_watched: bool, want_to_watch: bool, currently_watching: bool}
 */
function libraryFlags(array $index, int $tmdbId, string $mediaType): array
{
    $key = JsonStore::mediaKey($tmdbId, $mediaType);

    return $index[$key] ?? [
        'rating'             => null,
        'is_watched'         => false,
        'want_to_watch'      => false,
        'currently_watching' => false,
    ];
}

/**
 * Poster overlay: Currently Watching / Want to Watch / Watched plus the owner's score.
 *
 * @param array{rating?: ?int, is_watched?: bool, want_to_watch?: bool, currently_watching?: bool} $flags
 */
function renderLibraryOverlay(array $flags, string $size = ''): void
{
    $rating   = isset($flags['rating']) && $flags['rating'] !== null ? (int) $flags['rating'] : null;
    $watching = (bool) ($flags['currently_watching'] ?? false);
    $watched  = (bool) ($flags['is_watched'] ?? false) && !$watching;
    $want     = (bool) ($flags['want_to_watch'] ?? false) && !$watched && !$watching;

    if ($rating === null && !$watched && !$want && !$watching) {
        return;
    }

    $classes = 'library-overlay';
    if ($size !== '') {
        $classes .= ' library-overlay--' . $size;
    }

    echo '<div class="' . Security::escape($classes) . '">';

    if ($watching || $watched || $want) {
        $statusClass = $watching ? 'watching' : ($watched ? 'watched' : 'want');
        $statusLabel = $watching ? 'Currently' : ($watched ? 'Watched' : 'Want');
        echo '<span class="library-overlay__status library-overlay__status--'
            . Security::escape($statusClass) . '">'
            . Security::escape($statusLabel)
            . '</span>';
    } else {
        echo '<span></span>';
    }

    if ($rating !== null) {
        echo '<span class="library-overlay__score" aria-label="Your score: '
            . $rating . ' out of 10">'
            . '<svg class="library-overlay__score-star" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>'
            . '</svg>'
            . $rating
            . '</span>';
    }

    echo '</div>';
}

/**
 * Bitten-apple glyph used as the Apple TV availability mark.
 */
function appleTvLogoSvg(string $class = 'apple-tv-logo'): string
{
    return '<svg class="' . Security::escape($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path fill="currentColor" d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42C11.8 5.46 12.36 4.26 13 3.5z"/>'
        . '</svg>';
}

function renderAppleTvBadge(): void
{
    echo '<span class="apple-tv-badge" title="Available on Apple TV" aria-label="Available on Apple TV">'
        . appleTvLogoSvg()
        . '</span>';
}

function renderAppleTvLabel(): void
{
    echo '<p class="apple-tv-label">'
        . appleTvLogoSvg()
        . '<span>Available on Apple TV</span>'
        . '</p>';
}

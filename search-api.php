<?php

declare(strict_types=1);

/**
 * Live search suggestions for movies and TV shows.
 *
 * GET ?q= — returns a compact JSON list for the typeahead dropdown.
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * @param array<string, mixed> $payload
 */
function searchJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    searchJson(['success' => false, 'message' => 'Method not allowed.', 'results' => []], 405);
}

if (!$auth->isLoggedIn()) {
    searchJson(['success' => false, 'message' => 'Authentication required.', 'results' => []], 401);
}

$query = trim(preg_replace('/\s+/', ' ', getParam('q')) ?? '');
$query = mb_substr($query, 0, 80);

if (mb_strlen($query) < 2) {
    searchJson(['success' => true, 'query' => $query, 'results' => []]);
}

try {
    $items = $tmdb->search($query, 1);
} catch (Throwable $e) {
    searchJson([
        'success' => false,
        'message' => 'Unable to search titles right now.',
        'results' => [],
    ], 502);
}

$results = [];

foreach ($items as $item) {
    if (count($results) >= 8) {
        break;
    }

    $id = (int) ($item['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $type  = $tmdb->getMediaType($item, 'movie');
    $year  = $tmdb->getYear($item);
    $title = $tmdb->getTitle($item);

    $results[] = [
        'id'     => $id,
        'type'   => $type,
        'label'  => $tmdb->getMediaTypeLabel($item, $type),
        'title'  => $title,
        'year'   => $year,
        'rating' => $tmdb->formatRating($item['vote_average'] ?? null),
        'poster' => $tmdb->posterUrl($item['poster_path'] ?? null, 'w92'),
        'url'    => BASE_URL . '/details.php?type=' . rawurlencode($type) . '&id=' . $id,
    ];
}

searchJson([
    'success' => true,
    'query'   => $query,
    'results' => $results,
]);

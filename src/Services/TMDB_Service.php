<?php

declare(strict_types=1);

namespace Cinomnia\Services;

use RuntimeException;

/**
 * TMDB_Service - The Movie Database API Client
 *
 * Encapsulates all HTTP communication with TMDB using cURL.
 * Supports trending/popular content, genre filtering, search, and detail views.
 *
 * @see https://developer.themoviedb.org/reference/intro/getting-started
 */
final class TMDB_Service
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(
        string $apiKey = TMDB_API_KEY,
        string $baseUrl = TMDB_BASE_URL,
        int $timeout = 15
    ) {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    // -------------------------------------------------------------------------
    // Public API Methods
    // -------------------------------------------------------------------------

    /**
     * Fetch trending movies or TV shows for the current week.
     *
     * @param 'movie'|'tv' $mediaType
     * @return array<int, array<string, mixed>>
     */
    public function getTrending(string $mediaType = 'movie', int $page = 1): array
    {
        $endpoint = sprintf('/trending/%s/week', $mediaType);
        $response = $this->request($endpoint, ['page' => $page]);

        return $response['results'] ?? [];
    }

    /**
     * Fetch popular movies or TV shows.
     *
     * @param 'movie'|'tv' $mediaType
     * @return array<int, array<string, mixed>>
     */
    public function getPopular(string $mediaType = 'movie', int $page = 1): array
    {
        $endpoint = $mediaType === 'tv' ? '/tv/popular' : '/movie/popular';
        $response = $this->request($endpoint, ['page' => $page]);

        return $response['results'] ?? [];
    }

    /**
     * Discover movies or TV with combined filters (sort, genre, year, rating).
     *
     * TMDB discover parameters used:
     * - sort_by: popularity.desc | vote_average.desc | vote_average.asc
     * - with_genres, primary_release_date / first_air_date ranges
     * - vote_average.gte / vote_average.lte
     *
     * @param 'movie'|'tv' $mediaType
     * @param array{
     *     sort?: string,
     *     genre_id?: int,
     *     year_from?: int,
     *     year_to?: int,
     *     rating?: string
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function discover(string $mediaType, array $filters = [], int $page = 1): array
    {
        $endpoint = $mediaType === 'tv' ? '/discover/tv' : '/discover/movie';
        $params   = ['page' => $page];

        // --- Sort mapping ---
        $sort = $filters['sort'] ?? 'trending';
        $params['sort_by'] = match ($sort) {
            'highest' => 'vote_average.desc',
            'lowest'  => 'vote_average.asc',
            default   => 'popularity.desc', // closest to "trending" on discover
        };

        // Require minimum votes when sorting by rating (avoids 1-vote outliers)
        if (in_array($sort, ['highest', 'lowest'], true)) {
            $params['vote_count.gte'] = 50;
        }

        // --- Genre ---
        $genreId = (int) ($filters['genre_id'] ?? 0);
        if ($genreId > 0) {
            $params['with_genres'] = $genreId;
        }

        // --- Release year range ---
        $yearFrom = (int) ($filters['year_from'] ?? 0);
        $yearTo   = (int) ($filters['year_to'] ?? 0);

        if ($yearFrom > 0 && $yearTo > 0 && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $dateFromKey = $mediaType === 'tv' ? 'first_air_date.gte' : 'primary_release_date.gte';
        $dateToKey   = $mediaType === 'tv' ? 'first_air_date.lte' : 'primary_release_date.lte';

        if ($yearFrom > 0) {
            $params[$dateFromKey] = sprintf('%04d-01-01', $yearFrom);
        }
        if ($yearTo > 0) {
            $params[$dateToKey] = sprintf('%04d-12-31', $yearTo);
        }

        // --- Rating threshold (TMDB vote_average is 0–10) ---
        $rating = $filters['rating'] ?? '';
        match ($rating) {
            'below5' => $params['vote_average.lte'] = 4.9,
            'above5' => $params['vote_average.gte'] = 5.0,
            'above6' => $params['vote_average.gte'] = 6.0,
            'above7' => $params['vote_average.gte'] = 7.0,
            'above8' => $params['vote_average.gte'] = 8.0,
            'above9' => $params['vote_average.gte'] = 9.0,
            default  => null,
        };

        $response = $this->request($endpoint, $params);

        return $response['results'] ?? [];
    }

    /**
     * Whether discover filters require the /discover endpoint (not /trending).
     *
     * @param array{sort?: string, genre_id?: int, year_from?: int, year_to?: int, rating?: string} $filters
     */
    public function requiresDiscoverEndpoint(array $filters): bool
    {
        if (($filters['sort'] ?? 'trending') !== 'trending') {
            return true;
        }
        if ((int) ($filters['genre_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filters['year_from'] ?? 0) > 0 || (int) ($filters['year_to'] ?? 0) > 0) {
            return true;
        }
        if (($filters['rating'] ?? '') !== '') {
            return true;
        }

        return false;
    }

    /**
     * Search movies and TV shows by query string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $page = 1): array
    {
        if (trim($query) === '') {
            return [];
        }

        $response = $this->request('/search/multi', [
            'query'          => $query,
            'page'           => $page,
            'include_adult'  => 'false',
        ]);

        // Filter to movies and TV only (exclude people, etc.)
        $results = $response['results'] ?? [];

        return array_values(array_filter(
            $results,
            static fn(array $item): bool => in_array($item['media_type'] ?? '', ['movie', 'tv'], true)
        ));
    }

    /**
     * Fetch full details for a single movie by TMDB ID.
     *
     * @return array<string, mixed>|null
     */
    public function getMovieDetails(int $id): ?array
    {
        try {
            return $this->request("/movie/{$id}", [
                'append_to_response' => 'credits,videos',
            ]);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Fetch full details for a single TV show by TMDB ID.
     *
     * @return array<string, mixed>|null
     */
    public function getTvDetails(int $id): ?array
    {
        try {
            return $this->request("/tv/{$id}", [
                'append_to_response' => 'credits,videos',
            ]);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Extract the best YouTube trailer key from appended TMDB video results.
     */
    public function getYoutubeTrailerKey(array $details): ?string
    {
        $results = $details['videos']['results'] ?? [];
        $youtube = array_values(array_filter(
            $results,
            static fn(array $video): bool =>
                ($video['site'] ?? '') === 'YouTube' && !empty($video['key'])
        ));

        if ($youtube === []) {
            return null;
        }

        // Prefer official trailers, then teasers, then any YouTube clip
        foreach (['Trailer', 'Teaser', 'Clip', 'Featurette'] as $preferredType) {
            foreach ($youtube as $video) {
                if (($video['type'] ?? '') === $preferredType) {
                    return (string) $video['key'];
                }
            }
        }

        return (string) ($youtube[0]['key'] ?? null);
    }

    /**
     * Fetch a movie collection (franchise) and its parts.
     *
     * @return array<string, mixed>|null
     */
    public function getCollection(int $collectionId): ?array
    {
        try {
            return $this->request("/collection/{$collectionId}");
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Fetch all regular seasons (>= 1) with their episode lists for a TV show.
     *
     * Uses parallel cURL requests — one per season — to avoid sequential latency.
     *
     * @param array<int, array<string, mixed>> $seasonsMeta From TV details `seasons` key
     * @return array<int, array{season_number: int, name: string, episodes: array<int, array<string, mixed>>}>
     */
    public function getTvSeasonsWithEpisodes(int $tvId, array $seasonsMeta): array
    {
        $endpoints = [];

        foreach ($seasonsMeta as $season) {
            $seasonNumber = (int) ($season['season_number'] ?? -1);
            if ($seasonNumber >= 1) {
                $endpoints[$seasonNumber] = "/tv/{$tvId}/season/{$seasonNumber}";
            }
        }

        if ($endpoints === []) {
            return [];
        }

        ksort($endpoints);
        $responses = $this->requestMultiple($endpoints);
        $structured = [];

        foreach ($endpoints as $seasonNumber => $endpoint) {
            $detail = $responses[$seasonNumber] ?? null;
            if (!is_array($detail)) {
                continue;
            }

            $structured[] = [
                'season_number' => $seasonNumber,
                'name'          => $detail['name'] ?? "Season {$seasonNumber}",
                'episodes'      => $detail['episodes'] ?? [],
            ];
        }

        return $structured;
    }

    /**
     * Retrieve the list of genres for movies or TV shows.
     *
     * @param 'movie'|'tv' $mediaType
     * @return array<int, array{id: int, name: string}>
     */
    public function getGenres(string $mediaType = 'movie'): array
    {
        $endpoint = $mediaType === 'tv' ? '/genre/tv/list' : '/genre/movie/list';
        $response = $this->request($endpoint);

        return $response['genres'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Image URL Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a full poster image URL from a TMDB path fragment.
     */
    public function posterUrl(?string $path, string $size = TMDB_POSTER_GRID): string
    {
        if ($path === null || $path === '') {
            return BASE_URL . '/assets/no-poster.svg';
        }

        return TMDB_IMG_BASE . '/' . $size . $path;
    }

    /**
     * Build a full backdrop image URL.
     */
    public function backdropUrl(?string $path, string $size = TMDB_BACKDROP_SIZE): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return TMDB_IMG_BASE . '/' . $size . $path;
    }

    /**
     * Format TMDB vote_average (0–10) as a display rating.
     */
    public function formatRating(float|int|null $rating): string
    {
        if ($rating === null) {
            return 'N/A';
        }

        return number_format((float) $rating, 1);
    }

    /**
     * Extract display title from a movie or TV result array.
     */
    public function getTitle(array $item): string
    {
        return $item['title'] ?? $item['name'] ?? 'Unknown';
    }

    /**
     * Extract release year from a movie or TV result array.
     */
    public function getYear(array $item): string
    {
        $date = $item['release_date'] ?? $item['first_air_date'] ?? '';

        if ($date === '') {
            return '';
        }

        return substr($date, 0, 4);
    }

    /**
     * Resolve a result's raw media type key.
     *
     * @param 'movie'|'tv' $defaultType Fallback when the item omits media_type
     * @return 'movie'|'tv'
     */
    public function getMediaType(array $item, string $defaultType = 'movie'): string
    {
        $type = $item['media_type'] ?? $defaultType;
        return $type === 'tv' ? 'tv' : 'movie';
    }

    /**
     * Human-friendly label for a result's media type.
     */
    public function getMediaTypeLabel(array $item, string $defaultType = 'movie'): string
    {
        return $this->getMediaType($item, $defaultType) === 'tv' ? 'TV Show' : 'Movie';
    }

    /**
     * Full release / first-air date (YYYY-MM-DD), formatted for display.
     */
    public function formatDate(array|string|null $value): string
    {
        $date = is_array($value)
            ? ($value['release_date'] ?? $value['first_air_date'] ?? '')
            : (string) ($value ?? '');

        if ($date === '') {
            return 'TBA';
        }

        $timestamp = strtotime($date);
        return $timestamp !== false ? date('M j, Y', $timestamp) : $date;
    }

    /**
     * Format a runtime given in minutes as e.g. "2h 14m", "47m", or "—".
     */
    public function formatRuntime(int|float|null $minutes): string
    {
        $total = (int) $minutes;
        if ($total <= 0) {
            return '—';
        }

        $hours     = intdiv($total, 60);
        $remainder = $total % 60;

        if ($hours > 0) {
            return $remainder > 0 ? "{$hours}h {$remainder}m" : "{$hours}h";
        }

        return "{$total}m";
    }

    /**
     * Truncate an overview/synopsis for compact card display.
     */
    public function truncateOverview(?string $overview, int $maxLength = 140): string
    {
        $text = trim($overview ?? '');

        if ($text === '') {
            return 'No synopsis available.';
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    /**
     * Format season count for card display (e.g. "3 Seasons" or "—").
     */
    public function formatSeasonCount(int|null $count): string
    {
        if ($count === null || $count <= 0) {
            return '—';
        }

        return $count . ' Season' . ($count === 1 ? '' : 's');
    }

    /**
     * Format episode count for card display (e.g. "24 Episodes" or "—").
     */
    public function formatEpisodeCount(int|null $count): string
    {
        if ($count === null || $count <= 0) {
            return '—';
        }

        return $count . ' Episode' . ($count === 1 ? '' : 's');
    }

    // -------------------------------------------------------------------------
    // Detail Enrichment
    // -------------------------------------------------------------------------

    /**
     * Append per-item details to a list of results.
     *
     * The list endpoints (/trending, /discover, /search) do NOT include runtime
     * (movies) or season/episode counts (TV). We therefore fetch each item's
     * detail endpoint and merge the missing fields back in.
     *
     * To avoid N slow sequential round-trips, all detail requests are executed
     * in PARALLEL via curl_multi, so total latency is roughly that of one call.
     *
     * @param array<int, array<string, mixed>> $items
     * @param 'movie'|'tv' $defaultType Media type for items lacking media_type
     * @return array<int, array<string, mixed>> The same list, enriched in place
     */
    public function enrichWithDetails(array $items, string $defaultType = 'movie'): array
    {
        if ($items === []) {
            return $items;
        }

        // Build one detail-endpoint request per item, keyed by list position.
        $endpoints = [];
        foreach ($items as $index => $item) {
            $type = $this->getMediaType($item, $defaultType);
            $id   = (int) ($item['id'] ?? 0);

            if ($id > 0) {
                $endpoints[$index] = "/{$type}/{$id}";
            }
        }

        $responses = $this->requestMultiple($endpoints);

        // Merge the freshly fetched fields back into the original items.
        foreach ($responses as $index => $detail) {
            if (!is_array($detail)) {
                continue;
            }

            // Shared synopsis (list endpoints may include it; detail is authoritative)
            if (!empty($detail['overview'])) {
                $items[$index]['overview'] = $detail['overview'];
            } else {
                $items[$index]['overview'] ??= '';
            }

            // Movie-specific
            $items[$index]['runtime'] = $detail['runtime'] ?? null;

            // TV-specific
            $items[$index]['number_of_seasons']  = $detail['number_of_seasons'] ?? null;
            $items[$index]['number_of_episodes'] = $detail['number_of_episodes'] ?? null;

            // Ensure date fields are present for consistent display
            $items[$index]['release_date']   ??= $detail['release_date']   ?? null;
            $items[$index]['first_air_date'] ??= $detail['first_air_date'] ?? null;
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Internal HTTP Layer (cURL)
    // -------------------------------------------------------------------------

    /**
     * Execute a GET request against the TMDB API.
     *
     * @param array<string, scalar> $params Query parameters (api_key appended automatically)
     * @return array<string, mixed> Decoded JSON response
     * @throws RuntimeException On network or API errors
     */
    private function request(string $endpoint, array $params = []): array
    {
        $params['api_key']  = $this->apiKey;
        $params['language'] = $params['language'] ?? 'en-US';

        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Failed to initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
            // Verify SSL in production; disable only for local dev if needed
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('TMDB API request failed: ' . $curlError);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || !is_array($decoded)) {
            $message = is_array($decoded)
                ? ($decoded['status_message'] ?? 'Unknown API error')
                : 'Invalid JSON response';
            throw new RuntimeException("TMDB API error (HTTP {$httpCode}): {$message}");
        }

        return $decoded;
    }

    /**
     * Execute several GET requests concurrently using the cURL multi interface.
     *
     * This lets us fetch detail data for an entire page of results in roughly
     * the time of a single request, instead of blocking on each one in turn.
     * Individual failures are tolerated: a failed/invalid response yields null
     * for that key rather than throwing, so one bad item cannot break the grid.
     *
     * @param array<int|string, string> $endpoints Map of key => endpoint path
     * @return array<int|string, array<string, mixed>|null> Decoded responses, same keys
     */
    private function requestMultiple(array $endpoints): array
    {
        if ($endpoints === []) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles     = [];

        // Register one easy handle per endpoint on the shared multi handle.
        foreach ($endpoints as $key => $endpoint) {
            $url = $this->baseUrl . $endpoint . '?' . http_build_query([
                'api_key'  => $this->apiKey,
                'language' => 'en-US',
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $handles[$key] = $ch;
        }

        // Pump the event loop until every transfer has finished.
        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);
            if ($stillRunning) {
                // Block (up to 1s) until there is activity, avoiding a busy loop.
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($stillRunning && $status === CURLM_OK);

        // Harvest, decode, and clean up each handle.
        $results = [];
        foreach ($handles as $key => $ch) {
            $body     = curl_multi_getcontent($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $decoded  = is_string($body) ? json_decode($body, true) : null;

            $results[$key] = ($httpCode === 200 && is_array($decoded)) ? $decoded : null;

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}

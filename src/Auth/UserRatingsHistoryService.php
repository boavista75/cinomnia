<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Storage\JsonStore;

/**
 * UserRatingsHistoryService — ratings (1–10) and watched status per TMDB item.
 */
final class UserRatingsHistoryService
{
    private JsonStore $store;
    private CustomListService $customLists;

    public function __construct(JsonStore $store, CustomListService $customLists)
    {
        $this->store = $store;
        $this->customLists = $customLists;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, rating?: int}
     */
    public function setRating(
        int $userId,
        int $tmdbId,
        string $mediaType,
        int $rating,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        if ($rating < 1 || $rating > 10) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 10.'];
        }

        $this->store->mutate(function (array &$data) use ($tmdbId, $mediaType, $rating, $title, $posterPath): void {
            $key     = JsonStore::mediaKey($tmdbId, $mediaType);
            $current = $data['ratings'][$key] ?? [];
            $now     = JsonStore::now();

            $data['ratings'][$key] = [
                'tmdb_id'     => $tmdbId,
                'media_type'  => $mediaType,
                'rating'      => $rating,
                'is_watched'  => (bool) ($current['is_watched'] ?? false),
                'rated_at'    => $now,
                'watched_at'  => $current['watched_at'] ?? null,
                'title'       => $title ?? ($current['title'] ?? null),
                'poster_path' => $posterPath ?? ($current['poster_path'] ?? null),
                'created_at'  => $current['created_at'] ?? $now,
                'updated_at'  => $now,
            ];
        });

        unset($userId);

        return [
            'success' => true,
            'message' => 'Rating saved.',
            'rating'  => $rating,
        ];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string}
     */
    public function clearRating(int $userId, int $tmdbId, string $mediaType): array
    {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        $found = $this->store->mutate(function (array &$data) use ($tmdbId, $mediaType): bool {
            $key = JsonStore::mediaKey($tmdbId, $mediaType);

            if (!isset($data['ratings'][$key]) || $data['ratings'][$key]['rating'] === null) {
                return false;
            }

            $now = JsonStore::now();
            $data['ratings'][$key]['rating']     = null;
            $data['ratings'][$key]['rated_at']   = null;
            $data['ratings'][$key]['updated_at'] = $now;

            return true;
        });

        if (!$found) {
            return ['success' => false, 'message' => 'No rating found for this title.'];
        }

        unset($userId);

        return ['success' => true, 'message' => 'Rating cleared.'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, is_watched?: bool, in_want_to_watch?: bool}
     */
    public function toggleWatched(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        $current  = $this->getInteraction($userId, $tmdbId, $mediaType);
        $newState = !((bool) ($current['is_watched'] ?? false));

        return $this->setWatched($userId, $tmdbId, $mediaType, $newState, $title, $posterPath);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, is_watched?: bool, in_want_to_watch?: bool}
     */
    public function setWatched(
        int $userId,
        int $tmdbId,
        string $mediaType,
        bool $watched,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        $this->persistWatchedFlag($tmdbId, $mediaType, $watched, $title, $posterPath);

        if ($watched) {
            $this->customLists->syncWatchedListAdd($userId, $tmdbId, $mediaType, $title, $posterPath);
            $this->customLists->syncWantToWatchListRemove($userId, $tmdbId, $mediaType);
            $this->customLists->syncCurrentlyWatchingListRemove($userId, $tmdbId, $mediaType);
        } else {
            $this->customLists->syncWatchedListRemove($userId, $tmdbId, $mediaType);
        }

        return $this->libraryActionPayload(
            $userId,
            $tmdbId,
            $mediaType,
            $watched ? 'Marked as watched.' : 'Marked as not watched.'
        );
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, in_want_to_watch?: bool, is_watched?: bool}
     */
    public function toggleWantToWatch(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        if ($this->customLists->isItemInWantToWatchList($userId, $tmdbId, $mediaType)) {
            $this->customLists->syncWantToWatchListRemove($userId, $tmdbId, $mediaType);

            return $this->libraryActionPayload($userId, $tmdbId, $mediaType, 'Removed from Want to Watch.');
        }

        $interaction = $this->getInteraction($userId, $tmdbId, $mediaType);
        $wasWatched  = (bool) ($interaction['is_watched'] ?? false);

        if ($wasWatched) {
            $this->setWatched($userId, $tmdbId, $mediaType, false, $title, $posterPath);
        }

        $this->customLists->syncCurrentlyWatchingListRemove($userId, $tmdbId, $mediaType);
        $this->customLists->syncWantToWatchListAdd($userId, $tmdbId, $mediaType, $title, $posterPath);

        return $this->libraryActionPayload(
            $userId,
            $tmdbId,
            $mediaType,
            $wasWatched ? 'Moved from Watched to Want to Watch.' : 'Added to Want to Watch.'
        );
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, in_currently_watching?: bool, in_want_to_watch?: bool, is_watched?: bool}
     */
    public function toggleCurrentlyWatching(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if ($mediaType !== 'tv') {
            return ['success' => false, 'message' => 'Currently Watching is only available for TV shows.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        if ($this->customLists->isItemInCurrentlyWatchingList($userId, $tmdbId, $mediaType)) {
            $this->customLists->syncCurrentlyWatchingListRemove($userId, $tmdbId, $mediaType);

            return $this->libraryActionPayload($userId, $tmdbId, $mediaType, 'Removed from Currently Watching.');
        }

        $interaction = $this->getInteraction($userId, $tmdbId, $mediaType);
        $wasWatched  = (bool) ($interaction['is_watched'] ?? false);

        if ($wasWatched) {
            $this->setWatched($userId, $tmdbId, $mediaType, false, $title, $posterPath);
        }

        $this->customLists->syncWantToWatchListRemove($userId, $tmdbId, $mediaType);
        $this->customLists->syncCurrentlyWatchingListAdd($userId, $tmdbId, $mediaType, $title, $posterPath);

        return $this->libraryActionPayload(
            $userId,
            $tmdbId,
            $mediaType,
            $wasWatched ? 'Moved from Watched to Currently Watching.' : 'Added to Currently Watching.'
        );
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function clearWatchedStatus(int $userId, int $tmdbId, string $mediaType): void
    {
        unset($userId);

        if (!$this->isValidMediaType($mediaType) || $tmdbId <= 0) {
            return;
        }

        $this->persistWatchedFlag($tmdbId, $mediaType, false, null, null);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{
     *     rating: int|null,
     *     is_watched: bool,
     *     rated_at: string|null,
     *     watched_at: string|null,
     *     title: string|null,
     *     poster_path: string|null
     * }|null
     */
    public function getInteraction(int $userId, int $tmdbId, string $mediaType): ?array
    {
        unset($userId);

        if (!$this->isValidMediaType($mediaType)) {
            return null;
        }

        $row = $this->store->read()['ratings'][JsonStore::mediaKey($tmdbId, $mediaType)] ?? null;

        if (!is_array($row)) {
            return null;
        }

        return [
            'rating'      => $row['rating'] !== null ? (int) $row['rating'] : null,
            'is_watched'  => (bool) ($row['is_watched'] ?? false),
            'rated_at'    => $row['rated_at'] ?? null,
            'watched_at'  => $row['watched_at'] ?? null,
            'title'       => $row['title'] ?? null,
            'poster_path' => $row['poster_path'] ?? null,
        ];
    }

    /**
     * Library flags for every title the owner has rated, watched, or saved.
     *
     * @return array<string, array{rating: ?int, is_watched: bool, want_to_watch: bool, currently_watching: bool}>
     */
    public function getLibraryIndex(int $userId): array
    {
        $index = [];

        foreach ($this->store->read()['ratings'] as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $index[(string) $key] = [
                'rating'              => $row['rating'] !== null ? (int) $row['rating'] : null,
                'is_watched'          => (bool) ($row['is_watched'] ?? false),
                'want_to_watch'       => false,
                'currently_watching'  => false,
            ];
        }

        foreach ($this->customLists->getWantToWatchKeys($userId) as $key => $present) {
            unset($present);

            if (!isset($index[$key])) {
                $index[$key] = [
                    'rating'             => null,
                    'is_watched'         => false,
                    'want_to_watch'      => true,
                    'currently_watching' => false,
                ];
                continue;
            }

            $index[$key]['want_to_watch'] = true;
        }

        foreach ($this->customLists->getCurrentlyWatchingKeys($userId) as $key => $present) {
            unset($present);

            if (!isset($index[$key])) {
                $index[$key] = [
                    'rating'             => null,
                    'is_watched'         => false,
                    'want_to_watch'      => false,
                    'currently_watching' => true,
                ];
                continue;
            }

            $index[$key]['currently_watching'] = true;
        }

        return $index;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHistoryForUser(int $userId, ?int $limit = null): array
    {
        unset($userId);

        $rows = array_values($this->store->read()['ratings']);

        usort($rows, static fn(array $a, array $b): int =>
            strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function persistWatchedFlag(
        int $tmdbId,
        string $mediaType,
        bool $watched,
        ?string $title,
        ?string $posterPath
    ): void {
        $this->store->mutate(function (array &$data) use ($tmdbId, $mediaType, $watched, $title, $posterPath): void {
            $key     = JsonStore::mediaKey($tmdbId, $mediaType);
            $current = $data['ratings'][$key] ?? [];
            $now     = JsonStore::now();

            $data['ratings'][$key] = [
                'tmdb_id'     => $tmdbId,
                'media_type'  => $mediaType,
                'rating'      => $current['rating'] ?? null,
                'is_watched'  => $watched,
                'rated_at'    => $current['rated_at'] ?? null,
                'watched_at'  => $watched ? $now : null,
                'title'       => $title ?? ($current['title'] ?? null),
                'poster_path' => $posterPath ?? ($current['poster_path'] ?? null),
                'created_at'  => $current['created_at'] ?? $now,
                'updated_at'  => $now,
            ];
        });
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function isValidMediaType(string $mediaType): bool
    {
        return in_array($mediaType, ['movie', 'tv'], true);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, is_watched: bool, in_want_to_watch: bool, in_currently_watching: bool}
     */
    private function libraryActionPayload(int $userId, int $tmdbId, string $mediaType, string $message): array
    {
        $interaction = $this->getInteraction($userId, $tmdbId, $mediaType);

        return [
            'success'                => true,
            'message'                => $message,
            'is_watched'             => (bool) ($interaction['is_watched'] ?? false),
            'in_want_to_watch'       => $this->customLists->isItemInWantToWatchList($userId, $tmdbId, $mediaType),
            'in_currently_watching'  => $this->customLists->isItemInCurrentlyWatchingList($userId, $tmdbId, $mediaType),
        ];
    }
}

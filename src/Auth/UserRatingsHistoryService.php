<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Database\Database;
use PDO;

/**
 * UserRatingsHistoryService — user ratings (1–10) and watched status per TMDB item.
 */
final class UserRatingsHistoryService
{
    private CustomListService $customLists;

    public function __construct(CustomListService $customLists)
    {
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

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO user_ratings_history
                (user_id, tmdb_id, media_type, rating, rated_at, title, poster_path, created_at, updated_at)
             VALUES
                (:user_id, :tmdb_id, :media_type, :rating, NOW(), :title, :poster_path, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                rating      = VALUES(rating),
                rated_at    = NOW(),
                title       = COALESCE(VALUES(title), title),
                poster_path = COALESCE(VALUES(poster_path), poster_path),
                updated_at  = NOW()'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'tmdb_id'     => $tmdbId,
            'media_type'  => $mediaType,
            'rating'      => $rating,
            'title'       => $title,
            'poster_path' => $posterPath,
        ]);

        $this->syncLocalRating($userId, $tmdbId, $mediaType, $rating);
        $this->customLists->syncRatedListAdd($userId, $tmdbId, $mediaType, $title, $posterPath);

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

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE user_ratings_history
             SET rating = NULL, rated_at = NULL, updated_at = NOW()
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'No rating found for this title.'];
        }

        $this->removeLocalRating($userId, $tmdbId, $mediaType);
        $this->customLists->syncRatedListRemove($userId, $tmdbId, $mediaType);

        return ['success' => true, 'message' => 'Rating cleared.'];
    }

    /**
     * Flip watched status; creates a row if the user has not interacted with the title yet.
     *
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, is_watched?: bool}
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

        $current = $this->getInteraction($userId, $tmdbId, $mediaType);
        $newState = !((bool) ($current['is_watched'] ?? false));

        return $this->setWatched($userId, $tmdbId, $mediaType, $newState, $title, $posterPath);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, is_watched?: bool}
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

        $this->persistWatchedFlag($userId, $tmdbId, $mediaType, $watched, $title, $posterPath);

        if ($watched) {
            $this->customLists->syncWatchedListAdd($userId, $tmdbId, $mediaType, $title, $posterPath);
        } else {
            $this->customLists->syncWatchedListRemove($userId, $tmdbId, $mediaType);
        }

        return [
            'success'    => true,
            'message'    => $watched ? 'Marked as watched.' : 'Marked as not watched.',
            'is_watched' => $watched,
        ];
    }

    /**
     * Clears watched status after the title was removed from the watched list.
     * Does not touch list_items (already removed by the caller).
     *
     * @param 'movie'|'tv' $mediaType
     */
    public function clearWatchedStatus(int $userId, int $tmdbId, string $mediaType): void
    {
        if (!$this->isValidMediaType($mediaType) || $tmdbId <= 0) {
            return;
        }

        $this->persistWatchedFlag($userId, $tmdbId, $mediaType, false, null, null);
    }

    /**
     * Clears rating after the title was removed from the rated list.
     * Does not touch list_items (already removed by the caller).
     *
     * @param 'movie'|'tv' $mediaType
     */
    public function clearRatingStatus(int $userId, int $tmdbId, string $mediaType): void
    {
        if (!$this->isValidMediaType($mediaType) || $tmdbId <= 0) {
            return;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE user_ratings_history
             SET rating = NULL, rated_at = NULL, updated_at = NOW()
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        $this->removeLocalRating($userId, $tmdbId, $mediaType);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function persistWatchedFlag(
        int $userId,
        int $tmdbId,
        string $mediaType,
        bool $watched,
        ?string $title,
        ?string $posterPath
    ): void {
        $pdo = Database::getConnection();

        if ($watched) {
            $stmt = $pdo->prepare(
                'INSERT INTO user_ratings_history
                    (user_id, tmdb_id, media_type, is_watched, watched_at, title, poster_path, created_at, updated_at)
                 VALUES
                    (:user_id, :tmdb_id, :media_type, 1, NOW(), :title, :poster_path, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    is_watched  = 1,
                    watched_at  = NOW(),
                    title       = COALESCE(VALUES(title), title),
                    poster_path = COALESCE(VALUES(poster_path), poster_path),
                    updated_at  = NOW()'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO user_ratings_history
                    (user_id, tmdb_id, media_type, is_watched, watched_at, title, poster_path, created_at, updated_at)
                 VALUES
                    (:user_id, :tmdb_id, :media_type, 0, NULL, :title, :poster_path, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    is_watched  = 0,
                    watched_at  = NULL,
                    title       = COALESCE(VALUES(title), title),
                    poster_path = COALESCE(VALUES(poster_path), poster_path),
                    updated_at  = NOW()'
            );
        }

        $stmt->execute([
            'user_id'     => $userId,
            'tmdb_id'     => $tmdbId,
            'media_type'  => $mediaType,
            'title'       => $title,
            'poster_path' => $posterPath,
        ]);
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
        if (!$this->isValidMediaType($mediaType)) {
            return null;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT rating, is_watched, rated_at, watched_at, title, poster_path
             FROM user_ratings_history
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id AND media_type = :media_type
             LIMIT 1'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'rating'      => $row['rating'] !== null ? (int) $row['rating'] : null,
            'is_watched'  => (bool) $row['is_watched'],
            'rated_at'    => $row['rated_at'],
            'watched_at'  => $row['watched_at'],
            'title'       => $row['title'],
            'poster_path' => $row['poster_path'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHistoryForUser(int $userId, ?int $limit = null): array
    {
        $sql = 'SELECT tmdb_id, media_type, rating, is_watched, title, poster_path,
                       rated_at, watched_at, created_at, updated_at
                FROM user_ratings_history
                WHERE user_id = :user_id
                ORDER BY updated_at DESC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function isValidMediaType(string $mediaType): bool
    {
        return in_array($mediaType, ['movie', 'tv'], true);
    }

    /**
     * Mirror rating into local_ratings for community averages.
     *
     * @param 'movie'|'tv' $mediaType
     */
    private function syncLocalRating(int $userId, int $tmdbId, string $mediaType, int $rating): void
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO local_ratings (user_id, tmdb_id, media_type, rating_value, created_at, updated_at)
             VALUES (:user_id, :tmdb_id, :media_type, :rating_value, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                rating_value = VALUES(rating_value),
                updated_at   = NOW()'
        );
        $stmt->execute([
            'user_id'      => $userId,
            'tmdb_id'      => $tmdbId,
            'media_type'   => $mediaType,
            'rating_value' => $rating,
        ]);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function removeLocalRating(int $userId, int $tmdbId, string $mediaType): void
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM local_ratings
             WHERE user_id = :user_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Database\Database;
use PDO;
use PDOException;

/**
 * CustomListService — CRUD for user-owned lists and their TMDB items.
 */
final class CustomListService
{
    /** System-managed list synced when users mark titles as watched. */
    public const WATCHED_LIST_NAME = 'watched';

    /** System-managed list synced when users rate titles locally. */
    public const RATED_LIST_NAME = 'You Have Rated';

    private ?UserRatingsHistoryService $ratingsHistory = null;

    /**
     * Breaks the construction cycle: UserRatingsHistoryService depends on this service.
     */
    public function setRatingsHistoryService(UserRatingsHistoryService $ratingsHistory): void
    {
        $this->ratingsHistory = $ratingsHistory;
    }

    /**
     * @return array{success: bool, message: string, list_id?: int}
     */
    public function createList(int $userId, string $name): array
    {
        $name = $this->normalizeListName($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'List name is required.'];
        }

        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'List name must be 100 characters or fewer.'];
        }

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO custom_lists (user_id, name, created_at, updated_at)
                 VALUES (:user_id, :name, NOW(), NOW())'
            );
            $stmt->execute(['user_id' => $userId, 'name' => $name]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                return ['success' => false, 'message' => 'You already have a list with that name.'];
            }

            throw $e;
        }

        return [
            'success' => true,
            'message' => 'List created successfully.',
            'list_id' => (int) $pdo->lastInsertId(),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function renameList(int $userId, int $listId, string $name): array
    {
        if (!$this->userOwnsList($userId, $listId)) {
            return ['success' => false, 'message' => 'List not found.'];
        }

        if ($this->isWatchedList($userId, $listId)) {
            return ['success' => false, 'message' => 'The watched list cannot be renamed.'];
        }

        if ($this->isRatedList($userId, $listId)) {
            return ['success' => false, 'message' => 'The rated list cannot be renamed.'];
        }

        $name = $this->normalizeListName($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'List name is required.'];
        }

        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'List name must be 100 characters or fewer.'];
        }

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare(
                'UPDATE custom_lists SET name = :name, updated_at = NOW()
                 WHERE id = :list_id AND user_id = :user_id'
            );
            $stmt->execute(['name' => $name, 'list_id' => $listId, 'user_id' => $userId]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                return ['success' => false, 'message' => 'You already have a list with that name.'];
            }

            throw $e;
        }

        return ['success' => true, 'message' => 'List renamed successfully.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteList(int $userId, int $listId): array
    {
        if (!$this->userOwnsList($userId, $listId)) {
            return ['success' => false, 'message' => 'List not found.'];
        }

        if ($this->isWatchedList($userId, $listId)) {
            return ['success' => false, 'message' => 'The watched list cannot be deleted.'];
        }

        if ($this->isRatedList($userId, $listId)) {
            return ['success' => false, 'message' => 'The rated list cannot be deleted.'];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM custom_lists WHERE id = :list_id AND user_id = :user_id');
        $stmt->execute(['list_id' => $listId, 'user_id' => $userId]);

        return ['success' => true, 'message' => 'List deleted successfully.'];
    }

    /**
     * @return list<array{id: int, name: string, item_count: int, created_at: string, updated_at: string}>
     */
    /**
     * Lists the user can manually add titles to (excludes auto-managed system lists).
     *
     * @return list<array{id: int, name: string, item_count: int, created_at: string, updated_at: string}>
     */
    public function getSelectableListsForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->getListsForUser($userId),
            static fn(array $list): bool => !in_array(
                $list['name'],
                [self::WATCHED_LIST_NAME, self::RATED_LIST_NAME],
                true
            )
        ));
    }

    /**
     * Find or create the user's system "watched" list.
     */
    public function getOrCreateWatchedList(int $userId): int
    {
        $existingId = $this->findListIdByName($userId, self::WATCHED_LIST_NAME);

        if ($existingId !== null) {
            return $existingId;
        }

        $result = $this->createList($userId, self::WATCHED_LIST_NAME);

        if (!$result['success'] || !isset($result['list_id'])) {
            // Race: another request may have created it between check and insert.
            $existingId = $this->findListIdByName($userId, self::WATCHED_LIST_NAME);

            if ($existingId !== null) {
                return $existingId;
            }

            throw new \RuntimeException($result['message'] ?? 'Could not create watched list.');
        }

        return (int) $result['list_id'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncWatchedListAdd(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): void {
        $listId = $this->getOrCreateWatchedList($userId);
        $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);
    }

    /**
     * Find or create the user's system "You Have Rated" list.
     */
    public function getOrCreateRatedList(int $userId): int
    {
        $existingId = $this->findListIdByName($userId, self::RATED_LIST_NAME);

        if ($existingId !== null) {
            return $existingId;
        }

        $result = $this->createList($userId, self::RATED_LIST_NAME);

        if (!$result['success'] || !isset($result['list_id'])) {
            $existingId = $this->findListIdByName($userId, self::RATED_LIST_NAME);

            if ($existingId !== null) {
                return $existingId;
            }

            throw new \RuntimeException($result['message'] ?? 'Could not create rated list.');
        }

        return (int) $result['list_id'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncRatedListAdd(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): void {
        $listId = $this->getOrCreateRatedList($userId);
        $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncRatedListRemove(int $userId, int $tmdbId, string $mediaType): void
    {
        $listId = $this->findListIdByName($userId, self::RATED_LIST_NAME);

        if ($listId === null) {
            return;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM list_items
             WHERE list_id = :list_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'list_id'    => $listId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncWatchedListRemove(int $userId, int $tmdbId, string $mediaType): void
    {
        $listId = $this->findListIdByName($userId, self::WATCHED_LIST_NAME);

        if ($listId === null) {
            return;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM list_items
             WHERE list_id = :list_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'list_id'    => $listId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);
    }

    /**
     * @param list<int> $listIds
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, added: list<int>, skipped: list<int>}
     */
    public function addItemToLists(
        int $userId,
        array $listIds,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.', 'added' => [], 'skipped' => []];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.', 'added' => [], 'skipped' => []];
        }

        $added   = [];
        $skipped = [];

        foreach (array_unique(array_map('intval', $listIds)) as $listId) {
            if ($listId <= 0) {
                continue;
            }

            if ($this->isWatchedList($userId, $listId) || $this->isRatedList($userId, $listId)) {
                continue;
            }

            $result = $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);

            if (!$result['success']) {
                continue;
            }

            if (!empty($result['already_exists'])) {
                $skipped[] = $listId;
            } else {
                $added[] = $listId;
            }
        }

        if ($added === [] && $skipped === []) {
            return ['success' => false, 'message' => 'No valid lists selected.', 'added' => [], 'skipped' => []];
        }

        $message = $added !== []
            ? 'Added to ' . count($added) . ' list' . (count($added) === 1 ? '' : 's') . '.'
            : 'Already in the selected list(s).';

        return ['success' => true, 'message' => $message, 'added' => $added, 'skipped' => $skipped];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, list_id?: int, already_exists?: bool}
     */
    public function createListAndAddItem(
        int $userId,
        string $name,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): array {
        $createResult = $this->createList($userId, $name);

        if (!$createResult['success'] || !isset($createResult['list_id'])) {
            return $createResult;
        }

        $listId     = (int) $createResult['list_id'];
        $addResult  = $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);

        return [
            'success'        => true,
            'message'        => 'List created and title added.',
            'list_id'        => $listId,
            'already_exists' => $addResult['already_exists'] ?? false,
        ];
    }

    /**
     * Recent items grouped by list for dashboard poster previews.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function getPreviewItemsByList(int $userId, int $limitPerList = 5): array
    {
        if ($limitPerList < 1) {
            return [];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT li.list_id, li.tmdb_id, li.media_type, li.title, li.poster_path, li.added_at
             FROM list_items li
             INNER JOIN custom_lists cl ON cl.id = li.list_id
             WHERE cl.user_id = :user_id
             ORDER BY li.list_id ASC, li.added_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        $grouped = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $listId = (int) $row['list_id'];

            if (!isset($grouped[$listId])) {
                $grouped[$listId] = [];
            }

            if (count($grouped[$listId]) >= $limitPerList) {
                continue;
            }

            $grouped[$listId][] = $row;
        }

        return $grouped;
    }

    public function getListsForUser(int $userId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT cl.id, cl.name, cl.created_at, cl.updated_at,
                    COUNT(li.id) AS item_count
             FROM custom_lists cl
             LEFT JOIN list_items li ON li.list_id = cl.id
             WHERE cl.user_id = :user_id
             GROUP BY cl.id, cl.name, cl.created_at, cl.updated_at
             ORDER BY cl.updated_at DESC, cl.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $lists = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lists[] = [
                'id'         => (int) $row['id'],
                'name'       => $row['name'],
                'item_count' => (int) $row['item_count'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $lists;
    }

    /**
     * @return list<array<string, mixed>>|null Null when the list does not belong to the user.
     */
    public function getListItems(int $userId, int $listId): ?array
    {
        if (!$this->userOwnsList($userId, $listId)) {
            return null;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, tmdb_id, media_type, title, poster_path, added_at
             FROM list_items
             WHERE list_id = :list_id
             ORDER BY added_at DESC'
        );
        $stmt->execute(['list_id' => $listId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, already_exists?: bool}
     */
    public function addItem(
        int $userId,
        int $listId,
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

        if (!$this->userOwnsList($userId, $listId)) {
            return ['success' => false, 'message' => 'List not found.'];
        }

        if ($this->isItemInList($listId, $tmdbId, $mediaType)) {
            return [
                'success'        => true,
                'message'        => 'This title is already in the list.',
                'already_exists' => true,
            ];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO list_items (list_id, tmdb_id, media_type, title, poster_path, added_at)
             VALUES (:list_id, :tmdb_id, :media_type, :title, :poster_path, NOW())'
        );
        $stmt->execute([
            'list_id'     => $listId,
            'tmdb_id'     => $tmdbId,
            'media_type'  => $mediaType,
            'title'       => $title,
            'poster_path' => $posterPath,
        ]);

        $pdo->prepare(
            'UPDATE custom_lists SET updated_at = NOW() WHERE id = :list_id'
        )->execute(['list_id' => $listId]);

        return ['success' => true, 'message' => 'Added to your list.'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string}
     */
    public function removeItem(int $userId, int $listId, int $tmdbId, string $mediaType): array
    {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if (!$this->userOwnsList($userId, $listId)) {
            return ['success' => false, 'message' => 'List not found.'];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM list_items
             WHERE list_id = :list_id AND tmdb_id = :tmdb_id AND media_type = :media_type'
        );
        $stmt->execute([
            'list_id'    => $listId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Title was not in that list.'];
        }

        $pdo->prepare(
            'UPDATE custom_lists SET updated_at = NOW() WHERE id = :list_id'
        )->execute(['list_id' => $listId]);

        if ($this->isWatchedList($userId, $listId) && $this->ratingsHistory !== null) {
            $this->ratingsHistory->clearWatchedStatus($userId, $tmdbId, $mediaType);
        }

        if ($this->isRatedList($userId, $listId) && $this->ratingsHistory !== null) {
            $this->ratingsHistory->clearRatingStatus($userId, $tmdbId, $mediaType);
        }

        return ['success' => true, 'message' => 'Removed from your list.'];
    }

    public function isWatchedList(int $userId, int $listId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT name FROM custom_lists
             WHERE id = :list_id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['list_id' => $listId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row['name'] ?? '') === self::WATCHED_LIST_NAME;
    }

    public function isRatedList(int $userId, int $listId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT name FROM custom_lists
             WHERE id = :list_id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['list_id' => $listId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row['name'] ?? '') === self::RATED_LIST_NAME;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function isItemInList(int $listId, int $tmdbId, string $mediaType): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM list_items
             WHERE list_id = :list_id AND tmdb_id = :tmdb_id AND media_type = :media_type
             LIMIT 1'
        );
        $stmt->execute([
            'list_id'    => $listId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lists that already contain a given TMDB item for this user.
     *
     * @param 'movie'|'tv' $mediaType
     * @return list<int>
     */
    public function getListIdsContainingItem(int $userId, int $tmdbId, string $mediaType): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT li.list_id
             FROM list_items li
             INNER JOIN custom_lists cl ON cl.id = li.list_id
             WHERE cl.user_id = :user_id
               AND li.tmdb_id = :tmdb_id
               AND li.media_type = :media_type'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
        ]);

        return array_map(static fn(array $row): int => (int) $row['list_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function userOwnsList(int $userId, int $listId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM custom_lists WHERE id = :list_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['list_id' => $listId, 'user_id' => $userId]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findListIdByName(int $userId, string $name): ?int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM custom_lists
             WHERE user_id = :user_id AND name = :name
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['id'] : null;
    }

    private function normalizeListName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function isValidMediaType(string $mediaType): bool
    {
        return in_array($mediaType, ['movie', 'tv'], true);
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }
}

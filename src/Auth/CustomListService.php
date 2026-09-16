<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Storage\JsonStore;

/**
 * CustomListService — CRUD for owner lists and their TMDB items (JSON store).
 */
final class CustomListService
{
    public const WATCHED_LIST_NAME              = 'watched';
    public const WANT_TO_WATCH_LIST_NAME        = 'Want to Watch';
    public const CURRENTLY_WATCHING_LIST_NAME   = 'Currently Watching';

    /**
     * @return list<string>
     */
    public static function systemListNames(): array
    {
        return [
            self::WANT_TO_WATCH_LIST_NAME,
            self::CURRENTLY_WATCHING_LIST_NAME,
            self::WATCHED_LIST_NAME,
        ];
    }

    public static function isSystemListName(string $name): bool
    {
        return in_array($name, self::systemListNames(), true);
    }

    private JsonStore $store;
    private ?UserRatingsHistoryService $ratingsHistory = null;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
    }

    public function setRatingsHistoryService(UserRatingsHistoryService $ratingsHistory): void
    {
        $this->ratingsHistory = $ratingsHistory;
    }

    /**
     * @return array{success: bool, message: string, list_id?: int}
     */
    public function createList(int $userId, string $name): array
    {
        unset($userId);
        $name = $this->normalizeListName($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'List name is required.'];
        }

        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'List name must be 100 characters or fewer.'];
        }

        return $this->store->mutate(function (array &$data) use ($name): array {
            if ($this->findListIdByNameIn($data, $name) !== null) {
                return ['success' => false, 'message' => 'You already have a list with that name.'];
            }

            $listId = (int) $data['next_list_id'];
            $now    = JsonStore::now();

            $data['lists'][] = [
                'id'         => $listId,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
                'items'      => [],
            ];
            $data['next_list_id'] = $listId + 1;

            return [
                'success' => true,
                'message' => 'List created successfully.',
                'list_id' => $listId,
            ];
        });
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function renameList(int $userId, int $listId, string $name): array
    {
        unset($userId);

        if ($this->isWatchedList(0, $listId)) {
            return ['success' => false, 'message' => 'The watched list cannot be renamed.'];
        }

        if ($this->isWantToWatchList(0, $listId)) {
            return ['success' => false, 'message' => 'The Want to Watch list cannot be renamed.'];
        }

        if ($this->isCurrentlyWatchingList(0, $listId)) {
            return ['success' => false, 'message' => 'The Currently Watching list cannot be renamed.'];
        }

        $name = $this->normalizeListName($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'List name is required.'];
        }

        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'List name must be 100 characters or fewer.'];
        }

        return $this->store->mutate(function (array &$data) use ($listId, $name): array {
            $index = $this->findListIndex($data, $listId);

            if ($index === null) {
                return ['success' => false, 'message' => 'List not found.'];
            }

            $existingId = $this->findListIdByNameIn($data, $name);
            if ($existingId !== null && $existingId !== $listId) {
                return ['success' => false, 'message' => 'You already have a list with that name.'];
            }

            $data['lists'][$index]['name']       = $name;
            $data['lists'][$index]['updated_at'] = JsonStore::now();

            return ['success' => true, 'message' => 'List renamed successfully.'];
        });
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteList(int $userId, int $listId): array
    {
        unset($userId);

        if ($this->isWatchedList(0, $listId)) {
            return ['success' => false, 'message' => 'The watched list cannot be deleted.'];
        }

        if ($this->isWantToWatchList(0, $listId)) {
            return ['success' => false, 'message' => 'The Want to Watch list cannot be deleted.'];
        }

        if ($this->isCurrentlyWatchingList(0, $listId)) {
            return ['success' => false, 'message' => 'The Currently Watching list cannot be deleted.'];
        }

        return $this->store->mutate(function (array &$data) use ($listId): array {
            $index = $this->findListIndex($data, $listId);

            if ($index === null) {
                return ['success' => false, 'message' => 'List not found.'];
            }

            array_splice($data['lists'], $index, 1);

            return ['success' => true, 'message' => 'List deleted successfully.'];
        });
    }

    /**
     * @return list<array{id: int, name: string, item_count: int, created_at: string, updated_at: string}>
     */
    public function getSelectableListsForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->getListsForUser($userId),
            static fn(array $list): bool => !self::isSystemListName($list['name'])
        ));
    }

    public function getOrCreateWatchedList(int $userId): int
    {
        $existingId = $this->findListIdByName($userId, self::WATCHED_LIST_NAME);

        if ($existingId !== null) {
            return $existingId;
        }

        $result = $this->createList($userId, self::WATCHED_LIST_NAME);

        if (!$result['success'] || !isset($result['list_id'])) {
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
     * @param 'movie'|'tv' $mediaType
     */
    public function syncWatchedListRemove(int $userId, int $tmdbId, string $mediaType): void
    {
        unset($userId);
        $this->removeItemByName(self::WATCHED_LIST_NAME, $tmdbId, $mediaType);
    }

    public function getOrCreateWantToWatchList(int $userId): int
    {
        $existingId = $this->findListIdByName($userId, self::WANT_TO_WATCH_LIST_NAME);

        if ($existingId !== null) {
            return $existingId;
        }

        $result = $this->createList($userId, self::WANT_TO_WATCH_LIST_NAME);

        if (!$result['success'] || !isset($result['list_id'])) {
            $existingId = $this->findListIdByName($userId, self::WANT_TO_WATCH_LIST_NAME);

            if ($existingId !== null) {
                return $existingId;
            }

            throw new \RuntimeException($result['message'] ?? 'Could not create Want to Watch list.');
        }

        return (int) $result['list_id'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncWantToWatchListAdd(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): void {
        $listId = $this->getOrCreateWantToWatchList($userId);
        $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncWantToWatchListRemove(int $userId, int $tmdbId, string $mediaType): void
    {
        unset($userId);
        $this->removeItemByName(self::WANT_TO_WATCH_LIST_NAME, $tmdbId, $mediaType);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function isItemInWantToWatchList(int $userId, int $tmdbId, string $mediaType): bool
    {
        $listId = $this->findListIdByName($userId, self::WANT_TO_WATCH_LIST_NAME);

        if ($listId === null) {
            return false;
        }

        return $this->isItemInList($listId, $tmdbId, $mediaType);
    }

    /**
     * @return array<string, true> keyed by JsonStore::mediaKey()
     */
    public function getWantToWatchKeys(int $userId): array
    {
        unset($userId);

        return $this->getListItemKeysByName(self::WANT_TO_WATCH_LIST_NAME);
    }

    public function getOrCreateCurrentlyWatchingList(int $userId): int
    {
        $existingId = $this->findListIdByName($userId, self::CURRENTLY_WATCHING_LIST_NAME);

        if ($existingId !== null) {
            return $existingId;
        }

        $result = $this->createList($userId, self::CURRENTLY_WATCHING_LIST_NAME);

        if (!$result['success'] || !isset($result['list_id'])) {
            $existingId = $this->findListIdByName($userId, self::CURRENTLY_WATCHING_LIST_NAME);

            if ($existingId !== null) {
                return $existingId;
            }

            throw new \RuntimeException($result['message'] ?? 'Could not create Currently Watching list.');
        }

        return (int) $result['list_id'];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncCurrentlyWatchingListAdd(
        int $userId,
        int $tmdbId,
        string $mediaType,
        ?string $title = null,
        ?string $posterPath = null
    ): void {
        if ($mediaType !== 'tv') {
            return;
        }

        $listId = $this->getOrCreateCurrentlyWatchingList($userId);
        $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function syncCurrentlyWatchingListRemove(int $userId, int $tmdbId, string $mediaType): void
    {
        unset($userId);
        $this->removeItemByName(self::CURRENTLY_WATCHING_LIST_NAME, $tmdbId, $mediaType);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function isItemInCurrentlyWatchingList(int $userId, int $tmdbId, string $mediaType): bool
    {
        if ($mediaType !== 'tv') {
            return false;
        }

        $listId = $this->findListIdByName($userId, self::CURRENTLY_WATCHING_LIST_NAME);

        if ($listId === null) {
            return false;
        }

        return $this->isItemInList($listId, $tmdbId, $mediaType);
    }

    /**
     * @return array<string, true> keyed by JsonStore::mediaKey()
     */
    public function getCurrentlyWatchingKeys(int $userId): array
    {
        unset($userId);

        return $this->getListItemKeysByName(self::CURRENTLY_WATCHING_LIST_NAME);
    }

    /**
     * @return array<string, true> keyed by JsonStore::mediaKey()
     */
    private function getListItemKeysByName(string $listName): array
    {
        $keys = [];

        foreach ($this->store->read()['lists'] as $list) {
            if (($list['name'] ?? '') !== $listName) {
                continue;
            }

            foreach ($list['items'] ?? [] as $item) {
                $tmdbId    = (int) ($item['tmdb_id'] ?? 0);
                $mediaType = (string) ($item['media_type'] ?? '');

                if ($tmdbId > 0 && in_array($mediaType, ['movie', 'tv'], true)) {
                    $keys[JsonStore::mediaKey($tmdbId, $mediaType)] = true;
                }
            }

            break;
        }

        return $keys;
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

            if ($this->isSystemList($userId, $listId)) {
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
        if (self::isSystemListName($this->normalizeListName($name))) {
            return ['success' => false, 'message' => 'That list is managed automatically.'];
        }

        $createResult = $this->createList($userId, $name);

        if (!$createResult['success'] || !isset($createResult['list_id'])) {
            return $createResult;
        }

        $listId    = (int) $createResult['list_id'];
        $addResult = $this->addItem($userId, $listId, $tmdbId, $mediaType, $title, $posterPath);

        return [
            'success'        => true,
            'message'        => 'List created and title added.',
            'list_id'        => $listId,
            'already_exists' => $addResult['already_exists'] ?? false,
        ];
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function getPreviewItemsByList(int $userId, int $limitPerList = 5): array
    {
        unset($userId);

        if ($limitPerList < 1) {
            return [];
        }

        $grouped = [];

        foreach ($this->store->read()['lists'] as $list) {
            $listId = (int) ($list['id'] ?? 0);
            $items  = $list['items'] ?? [];

            usort($items, static fn(array $a, array $b): int =>
                strcmp((string) ($b['added_at'] ?? ''), (string) ($a['added_at'] ?? '')));

            $grouped[$listId] = array_slice($items, 0, $limitPerList);
        }

        return $grouped;
    }

    /**
     * @return list<array{id: int, name: string, item_count: int, created_at: string, updated_at: string}>
     */
    public function getListsForUser(int $userId): array
    {
        unset($userId);

        $lists = [];

        foreach ($this->store->read()['lists'] as $row) {
            $lists[] = [
                'id'         => (int) $row['id'],
                'name'       => (string) $row['name'],
                'item_count' => count($row['items'] ?? []),
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        usort($lists, static function (array $a, array $b): int {
            $cmp = strcmp($b['updated_at'], $a['updated_at']);

            return $cmp !== 0 ? $cmp : strcasecmp($a['name'], $b['name']);
        });

        return $lists;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getListItems(int $userId, int $listId): ?array
    {
        unset($userId);

        foreach ($this->store->read()['lists'] as $list) {
            if ((int) $list['id'] !== $listId) {
                continue;
            }

            $items = $list['items'] ?? [];
            usort($items, static fn(array $a, array $b): int =>
                strcmp((string) ($b['added_at'] ?? ''), (string) ($a['added_at'] ?? '')));

            return array_values($items);
        }

        return null;
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
        unset($userId);

        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        return $this->store->mutate(function (array &$data) use ($listId, $tmdbId, $mediaType, $title, $posterPath): array {
            $index = $this->findListIndex($data, $listId);

            if ($index === null) {
                return ['success' => false, 'message' => 'List not found.'];
            }

            foreach ($data['lists'][$index]['items'] as $item) {
                if ((int) $item['tmdb_id'] === $tmdbId && ($item['media_type'] ?? '') === $mediaType) {
                    return [
                        'success'        => true,
                        'message'        => 'This title is already in the list.',
                        'already_exists' => true,
                    ];
                }
            }

            $itemId = (int) $data['next_item_id'];
            $data['lists'][$index]['items'][] = [
                'id'          => $itemId,
                'tmdb_id'     => $tmdbId,
                'media_type'  => $mediaType,
                'title'       => $title,
                'poster_path' => $posterPath,
                'added_at'    => JsonStore::now(),
            ];
            $data['next_item_id'] = $itemId + 1;
            $data['lists'][$index]['updated_at'] = JsonStore::now();

            return ['success' => true, 'message' => 'Added to your list.'];
        });
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

        $removedFromWatched = false;

        $result = $this->store->mutate(function (array &$data) use ($listId, $tmdbId, $mediaType, &$removedFromWatched): array {
            $index = $this->findListIndex($data, $listId);

            if ($index === null) {
                return ['success' => false, 'message' => 'List not found.'];
            }

            $before = count($data['lists'][$index]['items']);
            $data['lists'][$index]['items'] = array_values(array_filter(
                $data['lists'][$index]['items'],
                static fn(array $item): bool =>
                    !((int) $item['tmdb_id'] === $tmdbId && ($item['media_type'] ?? '') === $mediaType)
            ));

            if (count($data['lists'][$index]['items']) === $before) {
                return ['success' => false, 'message' => 'Title was not in that list.'];
            }

            $data['lists'][$index]['updated_at'] = JsonStore::now();
            $listName = (string) $data['lists'][$index]['name'];
            $removedFromWatched = $listName === self::WATCHED_LIST_NAME;

            return ['success' => true, 'message' => 'Removed from your list.'];
        });

        if ($result['success'] && $this->ratingsHistory !== null) {
            if ($removedFromWatched) {
                $this->ratingsHistory->clearWatchedStatus($userId, $tmdbId, $mediaType);
            }
        }

        return $result;
    }

    public function isWatchedList(int $userId, int $listId): bool
    {
        unset($userId);

        foreach ($this->store->read()['lists'] as $list) {
            if ((int) $list['id'] === $listId) {
                return ($list['name'] ?? '') === self::WATCHED_LIST_NAME;
            }
        }

        return false;
    }

    public function isWantToWatchList(int $userId, int $listId): bool
    {
        unset($userId);

        foreach ($this->store->read()['lists'] as $list) {
            if ((int) $list['id'] === $listId) {
                return ($list['name'] ?? '') === self::WANT_TO_WATCH_LIST_NAME;
            }
        }

        return false;
    }

    public function isCurrentlyWatchingList(int $userId, int $listId): bool
    {
        unset($userId);

        foreach ($this->store->read()['lists'] as $list) {
            if ((int) $list['id'] === $listId) {
                return ($list['name'] ?? '') === self::CURRENTLY_WATCHING_LIST_NAME;
            }
        }

        return false;
    }

    public function isSystemList(int $userId, int $listId): bool
    {
        return $this->isWatchedList($userId, $listId)
            || $this->isWantToWatchList($userId, $listId)
            || $this->isCurrentlyWatchingList($userId, $listId);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public function isItemInList(int $listId, int $tmdbId, string $mediaType): bool
    {
        foreach ($this->store->read()['lists'] as $list) {
            if ((int) $list['id'] !== $listId) {
                continue;
            }

            foreach ($list['items'] ?? [] as $item) {
                if ((int) $item['tmdb_id'] === $tmdbId && ($item['media_type'] ?? '') === $mediaType) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return list<int>
     */
    public function getListIdsContainingItem(int $userId, int $tmdbId, string $mediaType): array
    {
        unset($userId);
        $ids = [];

        foreach ($this->store->read()['lists'] as $list) {
            foreach ($list['items'] ?? [] as $item) {
                if ((int) $item['tmdb_id'] === $tmdbId && ($item['media_type'] ?? '') === $mediaType) {
                    $ids[] = (int) $list['id'];
                    break;
                }
            }
        }

        return $ids;
    }

    public function userOwnsList(int $userId, int $listId): bool
    {
        unset($userId);

        return $this->findListIndex($this->store->read(), $listId) !== null;
    }

    public function findListIdByName(int $userId, string $name): ?int
    {
        unset($userId);

        return $this->findListIdByNameIn($this->store->read(), $name);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function removeItemByName(string $listName, int $tmdbId, string $mediaType): void
    {
        $this->store->mutate(function (array &$data) use ($listName, $tmdbId, $mediaType): void {
            $index = null;

            foreach ($data['lists'] as $i => $list) {
                if (($list['name'] ?? '') === $listName) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                return;
            }

            $data['lists'][$index]['items'] = array_values(array_filter(
                $data['lists'][$index]['items'],
                static fn(array $item): bool =>
                    !((int) $item['tmdb_id'] === $tmdbId && ($item['media_type'] ?? '') === $mediaType)
            ));
            $data['lists'][$index]['updated_at'] = JsonStore::now();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findListIdByNameIn(array $data, string $name): ?int
    {
        foreach ($data['lists'] ?? [] as $list) {
            if (($list['name'] ?? '') === $name) {
                return (int) $list['id'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findListIndex(array $data, int $listId): ?int
    {
        foreach ($data['lists'] ?? [] as $index => $list) {
            if ((int) ($list['id'] ?? 0) === $listId) {
                return (int) $index;
            }
        }

        return null;
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
}

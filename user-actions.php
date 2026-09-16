<?php

declare(strict_types=1);

/**
 * Cinomnia User Actions API
 *
 * JSON endpoint for custom lists, ratings, watched status, and notes.
 * Lives at the app root so it shares the same session cookie path as other pages.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed> $payload
 */
function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$csrfToken = Security::getRequestCsrfToken();

if (!Security::validateCsrfToken($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
}

$userId    = OWNER_USER_ID;
$action    = postParam('action');
$tmdbId    = (int) postParam('tmdb_id', '0');
$mediaType = postParam('media_type', 'movie');
$mediaType = in_array($mediaType, ['movie', 'tv'], true) ? $mediaType : 'movie';
$title     = postParam('title') !== '' ? postParam('title') : null;
$poster    = postParam('poster_path') !== '' ? postParam('poster_path') : null;

switch ($action) {
    case 'create_list':
        jsonResponse($customLists->createList($userId, postParam('name')));

    case 'rename_list':
        jsonResponse($customLists->renameList($userId, (int) postParam('list_id', '0'), postParam('name')));

    case 'delete_list':
        jsonResponse($customLists->deleteList($userId, (int) postParam('list_id', '0')));

    case 'get_lists':
        jsonResponse([
            'success' => true,
            'lists'   => $customLists->getSelectableListsForUser($userId),
        ]);

    case 'get_list_items':
        $listId = (int) postParam('list_id', '0');
        $items  = $customLists->getListItems($userId, $listId);

        if ($items === null) {
            jsonResponse(['success' => false, 'message' => 'List not found.'], 404);
        }

        jsonResponse(['success' => true, 'items' => $items]);

    case 'add_to_list':
        $listId = (int) postParam('list_id', '0');

        if ($customLists->isSystemList($userId, $listId)) {
            jsonResponse(['success' => false, 'message' => 'This list is managed automatically.']);
        }

        jsonResponse($customLists->addItem(
            $userId,
            $listId,
            $tmdbId,
            $mediaType,
            $title,
            $poster
        ));

    case 'add_to_lists':
        $rawIds = postParam('list_ids');
        $listIds = array_filter(
            array_map('intval', preg_split('/[\s,]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY) ?: [])
        );
        jsonResponse($customLists->addItemToLists(
            $userId,
            $listIds,
            $tmdbId,
            $mediaType,
            $title,
            $poster
        ));

    case 'create_list_and_add':
        jsonResponse($customLists->createListAndAddItem(
            $userId,
            postParam('name'),
            $tmdbId,
            $mediaType,
            $title,
            $poster
        ));

    case 'remove_from_list':
        jsonResponse($customLists->removeItem(
            $userId,
            (int) postParam('list_id', '0'),
            $tmdbId,
            $mediaType
        ));

    case 'set_rating':
        jsonResponse($userMedia->setRating(
            $userId,
            $tmdbId,
            $mediaType,
            (int) postParam('rating', '0'),
            $title,
            $poster
        ));

    case 'clear_rating':
        jsonResponse($userMedia->clearRating($userId, $tmdbId, $mediaType));

    case 'toggle_watched':
        jsonResponse($userMedia->toggleWatched($userId, $tmdbId, $mediaType, $title, $poster));

    case 'set_watched':
        $watched = filter_var(postParam('is_watched', '0'), FILTER_VALIDATE_BOOLEAN);
        jsonResponse($userMedia->setWatched($userId, $tmdbId, $mediaType, $watched, $title, $poster));

    case 'toggle_want_to_watch':
        jsonResponse($userMedia->toggleWantToWatch($userId, $tmdbId, $mediaType, $title, $poster));

    case 'toggle_currently_watching':
        jsonResponse($userMedia->toggleCurrentlyWatching($userId, $tmdbId, $mediaType, $title, $poster));

    case 'get_interaction':
        jsonResponse([
            'success'                 => true,
            'interaction'             => $userMedia->getInteraction($userId, $tmdbId, $mediaType),
            'list_ids'                => $customLists->getListIdsContainingItem($userId, $tmdbId, $mediaType),
            'lists'                   => $customLists->getSelectableListsForUser($userId),
            'in_want_to_watch'        => $customLists->isItemInWantToWatchList($userId, $tmdbId, $mediaType),
            'in_currently_watching'   => $customLists->isItemInCurrentlyWatchingList($userId, $tmdbId, $mediaType),
        ]);

    case 'get_history':
        $limit = (int) postParam('limit', '0');
        jsonResponse([
            'success' => true,
            'history' => $userMedia->getHistoryForUser($userId, $limit > 0 ? $limit : null),
        ]);

    case 'get_note':
        jsonResponse([
            'success' => true,
            'note'    => $notes->getNote($tmdbId, $mediaType),
        ]);

    case 'save_note':
        jsonResponse($notes->saveNote($tmdbId, $mediaType, postParam('body')));

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}

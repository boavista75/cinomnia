<?php

declare(strict_types=1);

/**
 * Cinomnia Comments API
 *
 * Public read; authenticated mutations require CSRF validation.
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

$action    = postParam('action');
$tmdbId    = (int) postParam('tmdb_id', '0');
$mediaType = postParam('media_type', 'movie');
$mediaType = in_array($mediaType, ['movie', 'tv'], true) ? $mediaType : 'movie';

$viewerId = $auth->isLoggedIn() ? (int) $auth->getUserId() : null;

switch ($action) {
    case 'get_comments':
        jsonResponse([
            'success'  => true,
            'comments' => $comments->getThreadedComments($tmdbId, $mediaType, $viewerId),
        ]);

    case 'post_comment':
        if (!$auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!Security::validateCsrfToken(Security::getRequestCsrfToken())) {
            jsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $parentId = (int) postParam('parent_comment_id', '0');
        $result   = $comments->postComment(
            (int) $auth->getUserId(),
            $tmdbId,
            $mediaType,
            postParam('body'),
            $parentId > 0 ? $parentId : null
        );

        jsonResponse($result, $result['success'] ? 200 : 400);

    case 'set_reaction':
        if (!$auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!Security::validateCsrfToken(Security::getRequestCsrfToken())) {
            jsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $result = $comments->setReaction(
            (int) $auth->getUserId(),
            (int) postParam('comment_id', '0'),
            (int) postParam('reaction_type', '0')
        );

        jsonResponse($result, $result['success'] ? 200 : 400);

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}

<?php

declare(strict_types=1);

/**
 * Cinomnia Admin Actions API
 *
 * Authenticated JSON endpoint restricted to admin users.
 * Handles user deletion and comment moderation via AJAX.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed> $payload
 */
function adminJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!$auth->isLoggedIn()) {
    adminJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int) $auth->getUserId();

if (!$admin->isAdmin($userId)) {
    adminJsonResponse(['success' => false, 'message' => 'Admin privileges required.'], 403);
}

if (!Security::validateCsrfToken(Security::getRequestCsrfToken())) {
    adminJsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
}

$action = postParam('action');

switch ($action) {
    case 'delete_user':
        adminJsonResponse($admin->deleteUser($userId, (int) postParam('user_id', '0')));

    case 'delete_comment':
        adminJsonResponse($admin->deleteComment($userId, (int) postParam('comment_id', '0')));

    default:
        adminJsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}

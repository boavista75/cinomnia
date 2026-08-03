<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

if (!$auth->isLoggedIn()) {
    redirect('/login.php?redirect=' . urlencode('/admin.php') . '&message=' . urlencode(
        'Please log in to access the admin panel.'
    ));
}

$adminUserId = (int) $auth->getUserId();

if (!$admin->isAdmin($adminUserId)) {
    http_response_code(403);
    $pageTitle = 'Access Denied';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="admin-denied">
        <div class="admin-denied__card">
            <h1 class="admin-denied__title">Access Denied</h1>
            <p class="admin-denied__text">
                You do not have permission to view this page.
                Admin privileges are required.
            </p>
            <a href="<?= Security::escape(BASE_URL) ?>/index.php" class="btn btn--primary">Return Home</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$message = '';
$error   = '';

// --- Handle form POST actions (add user, edit username) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken(postParam('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = postParam('action');

        switch ($action) {
            case 'create_user':
                $result  = $admin->createUser(
                    $adminUserId,
                    postParam('username'),
                    postParam('email'),
                    postParam('password'),
                    postParam('is_admin') === '1'
                );
                $message = $result['success'] ? $result['message'] : '';
                $error   = $result['success'] ? '' : $result['message'];
                break;

            case 'update_username':
                $result  = $admin->updateUsername(
                    $adminUserId,
                    (int) postParam('user_id', '0'),
                    postParam('username')
                );
                $message = $result['success'] ? $result['message'] : '';
                $error   = $result['success'] ? '' : $result['message'];
                break;
        }
    }
}

if ($message === '' && getParam('message') !== '') {
    $message = getParam('message');
}

// --- Active tab & sub-views ---
$allowedTabs = ['users', 'comments', 'ratings'];
$activeTab   = getParam('tab', 'users');
$activeTab   = in_array($activeTab, $allowedTabs, true) ? $activeTab : 'users';

$commentTmdbId    = (int) getParam('tmdb_id', '0');
$commentMediaType = getParam('media_type', 'movie');
$commentMediaType = in_array($commentMediaType, ['movie', 'tv'], true) ? $commentMediaType : 'movie';
$commentDetail    = $activeTab === 'comments' && $commentTmdbId > 0;

// --- Load data for active section ---
$stats            = $admin->getDashboardStats();
$users            = $activeTab === 'users' ? $admin->getAllUsers() : [];
$mediaWithComments = $activeTab === 'comments' && !$commentDetail
    ? $admin->getMediaWithComments()
    : [];
$commentsForMedia = $commentDetail
    ? $admin->getCommentsForMedia($commentTmdbId, $commentMediaType)
    : [];
$ratingsOverview  = $activeTab === 'ratings' ? $admin->getLocalRatingsOverview() : [];

// Resolve title for comment detail header
$commentMediaTitle = null;

if ($commentDetail) {
    foreach ($mediaWithComments as $item) {
        if ($item['tmdb_id'] === $commentTmdbId && $item['media_type'] === $commentMediaType) {
            $commentMediaTitle = $item['title'];
            break;
        }
    }

    if ($commentMediaTitle === null && $commentsForMedia !== []) {
        $allMedia = $admin->getMediaWithComments();

        foreach ($allMedia as $item) {
            if ($item['tmdb_id'] === $commentTmdbId && $item['media_type'] === $commentMediaType) {
                $commentMediaTitle = $item['title'];
                break;
            }
        }
    }
}

$pageTitle = 'Admin Panel';
$csrfToken = Security::generateCsrfToken();

require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= Security::escape(BASE_URL) ?>/css/admin.css">

<main class="admin-dashboard">
    <div class="admin-dashboard__inner">

        <?php if ($message !== ''): ?>
            <div class="alert alert--success admin-dashboard__alert" role="status"><?= Security::escape($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error admin-dashboard__alert" role="alert"><?= Security::escape($error) ?></div>
        <?php endif; ?>

        <header class="admin-dashboard__hero">
            <div>
                <p class="admin-dashboard__eyebrow">Cinomnia Control Center</p>
                <h1 class="admin-dashboard__title">Admin Panel</h1>
                <p class="admin-dashboard__subtitle">
                    Manage registered users, moderate comments, and review community ratings.
                </p>
            </div>
            <div class="admin-dashboard__stats">
                <div class="admin-stat">
                    <span class="admin-stat__value"><?= (int) $stats['users'] ?></span>
                    <span class="admin-stat__label">Users</span>
                </div>
                <div class="admin-stat">
                    <span class="admin-stat__value"><?= (int) $stats['total_comments'] ?></span>
                    <span class="admin-stat__label">Comments</span>
                </div>
                <div class="admin-stat">
                    <span class="admin-stat__value"><?= (int) $stats['commented_media'] ?></span>
                    <span class="admin-stat__label">Discussed</span>
                </div>
                <div class="admin-stat">
                    <span class="admin-stat__value"><?= (int) $stats['rated_media'] ?></span>
                    <span class="admin-stat__label">Rated</span>
                </div>
            </div>
        </header>

        <div class="admin-layout">
            <nav class="admin-sidebar" aria-label="Admin sections">
                <a href="<?= Security::escape(BASE_URL) ?>/admin.php?tab=users"
                   class="admin-sidebar__link<?= $activeTab === 'users' ? ' admin-sidebar__link--active' : '' ?>"
                   <?= $activeTab === 'users' ? 'aria-current="page"' : '' ?>>
                    <span class="admin-sidebar__icon" aria-hidden="true">👤</span>
                    User Management
                </a>
                <a href="<?= Security::escape(BASE_URL) ?>/admin.php?tab=comments"
                   class="admin-sidebar__link<?= $activeTab === 'comments' ? ' admin-sidebar__link--active' : '' ?>"
                   <?= $activeTab === 'comments' ? 'aria-current="page"' : '' ?>>
                    <span class="admin-sidebar__icon" aria-hidden="true">💬</span>
                    Comment Moderation
                </a>
                <a href="<?= Security::escape(BASE_URL) ?>/admin.php?tab=ratings"
                   class="admin-sidebar__link<?= $activeTab === 'ratings' ? ' admin-sidebar__link--active' : '' ?>"
                   <?= $activeTab === 'ratings' ? 'aria-current="page"' : '' ?>>
                    <span class="admin-sidebar__icon" aria-hidden="true">⭐</span>
                    Local Ratings
                </a>
            </nav>

            <section class="admin-content">

                <?php if ($activeTab === 'users'): ?>
                    <!-- ============================================================
                         USER MANAGEMENT
                         ============================================================ -->
                    <div class="admin-content__header">
                        <h2 class="admin-content__title">User Management</h2>
                        <span class="admin-content__count"><?= count($users) ?> registered</span>
                    </div>

                    <div class="admin-form-card">
                        <h3 class="admin-form-card__title">Add New User</h3>
                        <form method="post" action="<?= Security::escape(BASE_URL) ?>/admin.php?tab=users" class="admin-form-grid">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-group">
                                <label class="form-group__label" for="new-username">Username</label>
                                <input class="form-group__input" type="text" id="new-username" name="username"
                                       required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+"
                                       autocomplete="off" placeholder="johndoe">
                            </div>
                            <div class="form-group">
                                <label class="form-group__label" for="new-email">Email</label>
                                <input class="form-group__input" type="email" id="new-email" name="email"
                                       required autocomplete="off" placeholder="john@example.com">
                            </div>
                            <div class="form-group">
                                <label class="form-group__label" for="new-password">Password</label>
                                <input class="form-group__input" type="password" id="new-password" name="password"
                                       required minlength="8" autocomplete="new-password" placeholder="Min. 8 characters">
                            </div>
                            <div class="form-group">
                                <label class="admin-checkbox-label">
                                    <input type="checkbox" name="is_admin" value="1">
                                    Grant admin privileges
                                </label>
                            </div>
                            <button type="submit" class="btn btn--primary">Add User</button>
                        </form>
                    </div>

                    <?php if ($users === []): ?>
                        <div class="empty-state">No users registered yet.</div>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= (int) $user['id'] ?></td>
                                            <td><strong><?= Security::escape($user['username']) ?></strong></td>
                                            <td><?= Security::escape($user['email']) ?></td>
                                            <td>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="admin-badge admin-badge--admin">Admin</span>
                                                <?php else: ?>
                                                    <span class="admin-badge admin-badge--user">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= Security::escape(date('M j, Y', strtotime($user['created_at']))) ?></td>
                                            <td>
                                                <div class="admin-table__actions">
                                                    <button type="button"
                                                            class="btn btn--accent btn--sm"
                                                            data-admin-edit-user="<?= (int) $user['id'] ?>">
                                                        Edit
                                                    </button>
                                                    <?php if ((int) $user['id'] !== $adminUserId): ?>
                                                        <button type="button"
                                                                class="btn btn--accent btn--sm"
                                                                data-admin-delete-user="<?= (int) $user['id'] ?>"
                                                                data-username="<?= Security::escape($user['username']) ?>">
                                                            Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="admin-edit-row" id="edit-user-<?= (int) $user['id'] ?>">
                                            <td colspan="6">
                                                <form method="post"
                                                      action="<?= Security::escape(BASE_URL) ?>/admin.php?tab=users"
                                                      class="admin-edit-form">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="action" value="update_username">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <label class="form-group__label" for="edit-username-<?= (int) $user['id'] ?>">
                                                        New username for <?= Security::escape($user['username']) ?>:
                                                    </label>
                                                    <input class="form-group__input"
                                                           type="text"
                                                           id="edit-username-<?= (int) $user['id'] ?>"
                                                           name="username"
                                                           value="<?= Security::escape($user['username']) ?>"
                                                           required
                                                           minlength="3"
                                                           maxlength="50"
                                                           pattern="[a-zA-Z0-9_]+">
                                                    <button type="submit" class="btn btn--primary btn--sm">Save</button>
                                                    <button type="button" class="btn btn--secondary btn--sm" data-admin-cancel-edit>Cancel</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($activeTab === 'comments'): ?>
                    <!-- ============================================================
                         COMMENT MODERATION
                         ============================================================ -->
                    <?php if ($commentDetail): ?>
                        <a href="<?= Security::escape(BASE_URL) ?>/admin.php?tab=comments" class="admin-back-link">
                            ← Back to all commented titles
                        </a>
                        <div class="admin-content__header">
                            <h2 class="admin-content__title">
                                Comments:
                                <?= Security::escape($commentMediaTitle ?? 'TMDB #' . $commentTmdbId) ?>
                            </h2>
                            <span class="admin-content__count">
                                <span class="admin-badge admin-badge--<?= Security::escape($commentMediaType) ?>">
                                    <?= Security::escape($commentMediaType) ?>
                                </span>
                                <?= count($commentsForMedia) ?> comment<?= count($commentsForMedia) !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <?php if ($commentsForMedia === []): ?>
                            <div class="empty-state">No comments found for this title.</div>
                        <?php else: ?>
                            <ul class="admin-comment-list">
                                <?php foreach ($commentsForMedia as $comment): ?>
                                    <li class="admin-comment-card<?= $comment['is_reply'] ? ' admin-comment-card--reply' : '' ?>">
                                        <div class="admin-comment-card__header">
                                            <span class="admin-comment-card__author">
                                                <?= Security::escape($comment['username']) ?>
                                                <?php if ($comment['is_reply']): ?>
                                                    <span class="admin-badge admin-badge--user">Reply</span>
                                                <?php endif; ?>
                                            </span>
                                            <time class="admin-comment-card__time" datetime="<?= Security::escape($comment['created_at']) ?>">
                                                <?= Security::escape(date('M j, Y g:i A', strtotime($comment['created_at']))) ?>
                                            </time>
                                        </div>
                                        <?php if ($comment['is_reply'] && $comment['parent_username']): ?>
                                            <p class="admin-comment-card__reply-to">
                                                Replying to <?= Security::escape($comment['parent_username']) ?>:
                                                “<?= Security::escape(mb_strimwidth($comment['parent_body'] ?? '', 0, 80, '…')) ?>”
                                            </p>
                                        <?php endif; ?>
                                        <p class="admin-comment-card__body"><?= Security::escape($comment['body']) ?></p>
                                        <div class="admin-comment-card__actions">
                                            <button type="button"
                                                    class="btn btn--accent btn--sm"
                                                    data-admin-delete-comment="<?= (int) $comment['id'] ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="admin-content__header">
                            <h2 class="admin-content__title">Comment Moderation</h2>
                            <span class="admin-content__count"><?= count($mediaWithComments) ?> titles with comments</span>
                        </div>

                        <?php if ($mediaWithComments === []): ?>
                            <div class="empty-state">No comments have been posted yet.</div>
                        <?php else: ?>
                            <ul class="admin-media-list">
                                <?php foreach ($mediaWithComments as $item): ?>
                                    <li class="admin-media-item">
                                        <div class="admin-media-item__info">
                                            <span class="admin-badge admin-badge--<?= Security::escape($item['media_type']) ?>">
                                                <?= Security::escape($item['media_type']) ?>
                                            </span>
                                            <span class="admin-media-item__title">
                                                <?= Security::escape($item['title'] ?? 'TMDB #' . $item['tmdb_id']) ?>
                                            </span>
                                            <span class="admin-media-item__meta">
                                                <?= (int) $item['comment_count'] ?> comment<?= (int) $item['comment_count'] !== 1 ? 's' : '' ?>
                                                · Last activity <?= Security::escape(date('M j, Y', strtotime($item['last_comment_at']))) ?>
                                            </span>
                                        </div>
                                        <a class="admin-media-item__link"
                                           href="<?= Security::escape(BASE_URL) ?>/admin.php?tab=comments&amp;tmdb_id=<?= (int) $item['tmdb_id'] ?>&amp;media_type=<?= Security::escape($item['media_type']) ?>">
                                            View Comments →
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($activeTab === 'ratings'): ?>
                    <!-- ============================================================
                         LOCAL RATINGS OVERVIEW
                         ============================================================ -->
                    <div class="admin-content__header">
                        <h2 class="admin-content__title">Local Ratings Overview</h2>
                        <span class="admin-content__count"><?= count($ratingsOverview) ?> rated titles</span>
                    </div>

                    <?php if ($ratingsOverview === []): ?>
                        <div class="empty-state">No local ratings have been submitted yet.</div>
                    <?php else: ?>
                        <div class="admin-ratings-list">
                            <?php foreach ($ratingsOverview as $item): ?>
                                <article class="admin-rating-card">
                                    <div class="admin-rating-card__header"
                                         data-admin-rating-toggle
                                         role="button"
                                         tabindex="0"
                                         aria-expanded="false">
                                        <div class="admin-rating-card__title-wrap">
                                            <span class="admin-badge admin-badge--<?= Security::escape($item['media_type']) ?>">
                                                <?= Security::escape($item['media_type']) ?>
                                            </span>
                                            <h3 class="admin-rating-card__title">
                                                <?= Security::escape($item['title'] ?? 'TMDB #' . $item['tmdb_id']) ?>
                                            </h3>
                                            <span class="admin-rating-card__avg">
                                                <?= number_format((float) $item['avg_rating'], 1) ?><small>/10</small>
                                            </span>
                                        </div>
                                        <span class="admin-rating-card__toggle">
                                            <?= (int) $item['rating_count'] ?> rating<?= (int) $item['rating_count'] !== 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                    <div class="admin-rating-card__body">
                                        <div class="admin-table-wrap">
                                            <table class="admin-table">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Rating</th>
                                                        <th>Rated On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($item['ratings'] as $rating): ?>
                                                        <tr>
                                                            <td><?= Security::escape($rating['username']) ?></td>
                                                            <td>
                                                                <strong><?= (int) $rating['rating_value'] ?></strong> / 10
                                                            </td>
                                                            <td>
                                                                <?= Security::escape(date('M j, Y g:i A', strtotime($rating['updated_at']))) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </section>
        </div>
    </div>
</main>

<script>
    window.CINOMNIA_ADMIN = {
        csrfToken: <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>,
        apiUrl: <?= json_encode(BASE_URL . '/admin-actions.php', JSON_THROW_ON_ERROR) ?>
    };
</script>
<script src="<?= Security::escape(BASE_URL) ?>/js/admin.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>

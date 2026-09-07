<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Auth\CustomListService;
use Cinomnia\Security\Security;

if (!$auth->isLoggedIn()) {
    redirect('/login.php?redirect=' . urlencode('/lists.php') . '&message=' . urlencode(
        'Please log in to manage your custom lists.'
    ));
}

$userId  = (int) $auth->getUserId();
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken(postParam('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = postParam('action');
        $listId = (int) postParam('list_id', '0');

        switch ($action) {
            case 'create_list':
                $result  = $customLists->createList($userId, postParam('name'));
                $message = $result['success'] ? $result['message'] : '';
                $error   = $result['success'] ? '' : $result['message'];
                break;

            case 'rename_list':
                $result  = $customLists->renameList($userId, $listId, postParam('name'));
                $message = $result['success'] ? $result['message'] : '';
                $error   = $result['success'] ? '' : $result['message'];
                break;

            case 'delete_list':
                $result  = $customLists->deleteList($userId, $listId);
                $message = $result['success'] ? $result['message'] : '';
                $error   = $result['success'] ? '' : $result['message'];

                if ($result['success']) {
                    redirect('/lists.php?message=' . urlencode($result['message']));
                }
                break;

            case 'remove_from_list':
                $result  = $customLists->removeItem(
                    $userId,
                    $listId,
                    (int) postParam('tmdb_id', '0'),
                    postParam('media_type', 'movie')
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

$lists         = $customLists->getListsForUser($userId);
$listPreviews  = $customLists->getPreviewItemsByList($userId, 5);
$selectedListId = (int) getParam('list_id', '0');
$selectedItems  = null;
$selectedList   = null;

usort($lists, static function (array $a, array $b): int {
    $systemOrder = [
        CustomListService::WANT_TO_WATCH_LIST_NAME => 0,
        CustomListService::WATCHED_LIST_NAME       => 1,
        CustomListService::RATED_LIST_NAME         => 2,
    ];

    $aRank = $systemOrder[$a['name']] ?? 99;
    $bRank = $systemOrder[$b['name']] ?? 99;

    if ($aRank !== $bRank) {
        return $aRank <=> $bRank;
    }

    return strcmp($b['updated_at'], $a['updated_at']);
});

if ($selectedListId > 0) {
    $selectedItems = $customLists->getListItems($userId, $selectedListId);

    foreach ($lists as $list) {
        if ((int) $list['id'] === $selectedListId) {
            $selectedList = $list;
            break;
        }
    }
}

/**
 * @param array<string, mixed> $list
 */
function formatListDate(array $list): string
{
    $raw = $list['created_at'] ?? '';

    if ($raw === '') {
        return 'Unknown date';
    }

    $timestamp = strtotime($raw);

    return $timestamp !== false ? date('M j, Y', $timestamp) : $raw;
}

function formatListDisplayName(string $name): string
{
    return match ($name) {
        CustomListService::WANT_TO_WATCH_LIST_NAME => 'Want to Watch',
        CustomListService::WATCHED_LIST_NAME       => 'Watched',
        CustomListService::RATED_LIST_NAME         => 'You Have Rated',
        default                                    => $name,
    };
}

function getSystemListSyncNote(string $name): ?string
{
    return match ($name) {
        CustomListService::WANT_TO_WATCH_LIST_NAME => 'Synced from the Want to Watch button',
        CustomListService::WATCHED_LIST_NAME       => 'Synced from watched status',
        CustomListService::RATED_LIST_NAME         => 'Synced from your ratings',
        default                                    => null,
    };
}

function isSystemListName(string $name): bool
{
    return CustomListService::isSystemListName($name);
}

$pageTitle = 'My Lists';

require __DIR__ . '/includes/header.php';
?>

<main class="lists-dashboard">
    <div class="lists-dashboard__inner">

        <!-- Hero header -->
        <header class="lists-dashboard__hero">
            <div class="lists-dashboard__hero-copy">
                <p class="lists-dashboard__eyebrow">Your collections</p>
                <h1 class="lists-dashboard__title">My Lists</h1>
                <p class="lists-dashboard__subtitle">
                    Organize everything you want to watch, have watched, or love — all in one place.
                </p>
            </div>

            <form method="POST" action="" class="lists-dashboard__create">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="create_list">
                <label class="lists-dashboard__create-label" for="new-list-name">New list</label>
                <div class="lists-dashboard__create-row">
                    <input type="text"
                           id="new-list-name"
                           name="name"
                           class="lists-dashboard__create-input"
                           placeholder="Weekend Watchlist, Top Sci-Fi…"
                           maxlength="100"
                           required>
                    <button type="submit" class="btn btn--primary lists-dashboard__create-btn">
                        + Create List
                    </button>
                </div>
            </form>
        </header>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error lists-dashboard__alert" role="alert"><?= Security::escape($error) ?></div>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert--success lists-dashboard__alert" role="alert"><?= Security::escape($message) ?></div>
        <?php endif; ?>

        <?php if (empty($lists)): ?>
            <section class="lists-dashboard__empty-state">
                <div class="lists-dashboard__empty-icon" aria-hidden="true">&#127910;</div>
                <h2 class="lists-dashboard__empty-title">No lists yet</h2>
                <p class="lists-dashboard__empty-text">
                    Create your first collection above, then add titles from any movie or TV detail page.
                </p>
            </section>
        <?php else: ?>
            <!-- List cards grid -->
            <section class="lists-dashboard__cards" aria-label="Your lists">
                <?php foreach ($lists as $list): ?>
                    <?php
                    $listId         = (int) $list['id'];
                    $isSystemList   = isSystemListName($list['name']);
                    $displayName    = formatListDisplayName($list['name']);
                    $systemSyncNote = getSystemListSyncNote($list['name']);
                    $itemCount      = (int) $list['item_count'];
                    $previews       = $listPreviews[$listId] ?? [];
                    $isSelected     = $selectedListId === $listId;
                    $detailUrl      = BASE_URL . '/lists.php?list_id=' . $listId;
                    $countLabel     = $itemCount === 1 ? '1 item' : $itemCount . ' items';
                    $remaining      = max(0, $itemCount - count($previews));
                    ?>
                    <article class="list-card<?= $isSelected ? ' list-card--active' : '' ?><?= $isSystemList ? ' list-card--system' : '' ?>">
                        <div class="list-card__top">
                            <div class="list-card__heading<?= $isSystemList ? ' list-card__heading--system' : '' ?>">
                                <?php if ($isSystemList): ?>
                                    <span class="list-card__badge list-card__badge--synced">Auto-synced</span>
                                <?php else: ?>
                                    <span class="list-card__badge">Custom playlist</span>
                                <?php endif; ?>
                                <h2 class="list-card__title"><?= Security::escape($displayName) ?></h2>
                                <p class="list-card__meta">
                                    <span><?= Security::escape($countLabel) ?></span>
                                    <span class="list-card__meta-dot" aria-hidden="true">&middot;</span>
                                    <span>Created <?= Security::escape(formatListDate($list)) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="list-card__previews<?= $isSystemList ? ' list-card__previews--system' : '' ?>"
                             aria-label="Preview of titles in this list">
                            <?php if ($itemCount === 0): ?>
                                <div class="list-card__previews-empty">
                                    <span>No titles yet</span>
                                </div>
                            <?php else: ?>
                                <div class="list-card__poster-row">
                                    <?php foreach ($previews as $preview): ?>
                                        <?php
                                        $previewUrl = BASE_URL . '/details.php?type='
                                            . urlencode($preview['media_type']) . '&id=' . (int) $preview['tmdb_id'];
                                        ?>
                                        <a href="<?= Security::escape($previewUrl) ?>"
                                           class="list-card__thumb"
                                           title="<?= Security::escape($preview['title'] ?? 'Untitled') ?>">
                                            <img src="<?= Security::escape($tmdb->posterUrl($preview['poster_path'] ?? null, 'w185')) ?>"
                                                 alt=""
                                                 loading="lazy"
                                                 width="92"
                                                 height="138">
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if ($remaining > 0): ?>
                                        <a href="<?= Security::escape($detailUrl) ?>"
                                           class="list-card__thumb list-card__thumb--more"
                                           title="View all items">
                                            +<?= $remaining ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="list-card__actions<?= $isSystemList ? ' list-card__actions--system' : '' ?>">
                            <?php if ($isSystemList): ?>
                                <div class="list-card__system-actions">
                                    <a href="<?= Security::escape($detailUrl) ?>#list-detail"
                                       class="btn btn--primary btn--sm list-card__btn">
                                        View Details
                                    </a>
                                    <p class="list-card__system-note"><?= Security::escape($systemSyncNote ?? '') ?></p>
                                </div>
                            <?php else: ?>
                                <a href="<?= Security::escape($detailUrl) ?>#list-detail"
                                   class="btn btn--primary btn--sm list-card__btn">
                                    View Details
                                </a>
                                <button type="button"
                                        class="btn btn--secondary btn--sm list-card__btn"
                                        data-rename-list
                                        data-list-id="<?= $listId ?>"
                                        data-list-name="<?= Security::escape($list['name']) ?>">
                                    Rename
                                </button>
                                <form method="POST"
                                      action=""
                                      class="list-card__delete-form"
                                      onsubmit="return confirm('Delete this list and all its items?');">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="delete_list">
                                    <input type="hidden" name="list_id" value="<?= $listId ?>">
                                    <button type="submit" class="btn btn--danger btn--sm list-card__btn">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- Detail panel for selected list -->
        <?php if ($selectedList !== null): ?>
            <?php
            $isSystemDetail  = isSystemListName($selectedList['name']);
            $detailSyncNote  = getSystemListSyncNote($selectedList['name']);
            ?>
            <section class="list-detail" id="list-detail" aria-labelledby="list-detail-title">
                <div class="list-detail__header">
                    <div class="list-detail__intro">
                        <p class="list-detail__eyebrow">List details</p>
                        <h2 class="list-detail__title" id="list-detail-title">
                            <?= Security::escape(formatListDisplayName($selectedList['name'])) ?>
                        </h2>
                        <p class="list-detail__meta">
                            <?= Security::escape((int) $selectedList['item_count'] === 1 ? '1 item' : (int) $selectedList['item_count'] . ' items') ?>
                            &middot; Created <?= Security::escape(formatListDate($selectedList)) ?>
                        </p>
                        <?php if ($isSystemDetail && $detailSyncNote !== null): ?>
                            <p class="list-detail__note">
                                <?= Security::escape($detailSyncNote) ?>.
                                <?php if ($selectedList['name'] === CustomListService::WATCHED_LIST_NAME): ?>
                                    Removing a title here also clears its watched status on the details page.
                                <?php elseif ($selectedList['name'] === CustomListService::RATED_LIST_NAME): ?>
                                    Removing a title here also clears its rating on the details page.
                                <?php else: ?>
                                    Marking a title as watched automatically removes it from this list.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="list-detail__header-actions">
                        <?php if (!$isSystemDetail): ?>
                            <button type="button"
                                    class="btn btn--secondary btn--sm"
                                    data-rename-list
                                    data-list-id="<?= (int) $selectedList['id'] ?>"
                                    data-list-name="<?= Security::escape($selectedList['name']) ?>">
                                Rename List
                            </button>
                            <form method="POST"
                                  action=""
                                  onsubmit="return confirm('Delete this list and all its items?');">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="delete_list">
                                <input type="hidden" name="list_id" value="<?= (int) $selectedList['id'] ?>">
                                <button type="submit" class="btn btn--danger btn--sm">Delete List</button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= Security::escape(BASE_URL) ?>/lists.php" class="btn btn--ghost btn--sm">Close</a>
                    </div>
                </div>

                <?php if ($selectedItems === null): ?>
                    <div class="alert alert--error" role="alert">List not found.</div>
                <?php elseif (empty($selectedItems)): ?>
                    <div class="list-detail__empty">
                        <p>
                            <?php if ($selectedList['name'] === CustomListService::WANT_TO_WATCH_LIST_NAME): ?>
                                This list is empty. Open a title and tap <strong>Want to Watch</strong>.
                            <?php else: ?>
                                This list is empty. Browse titles and use <strong>Add to List</strong> on a detail page.
                            <?php endif; ?>
                        </p>
                        <a href="<?= Security::escape(BASE_URL) ?>/index.php" class="btn btn--primary">Browse Titles</a>
                    </div>
                <?php else: ?>
                    <ul class="list-detail__grid">
                        <?php foreach ($selectedItems as $item): ?>
                            <?php
                            $itemTmdbId = (int) $item['tmdb_id'];
                            $itemType   = $item['media_type'];
                            $itemTitle  = $item['title'] ?? 'Untitled';
                            $itemPoster = $tmdb->posterUrl($item['poster_path'] ?? null, TMDB_POSTER_GRID);
                            $detailUrl  = BASE_URL . '/details.php?type=' . urlencode($itemType) . '&id=' . $itemTmdbId;
                            ?>
                            <li class="list-detail__item">
                                <a href="<?= Security::escape($detailUrl) ?>" class="list-detail__item-link">
                                    <div class="list-detail__poster-wrap">
                                        <img src="<?= Security::escape($itemPoster) ?>"
                                             alt=""
                                             class="list-detail__poster"
                                             loading="lazy"
                                             width="342"
                                             height="513">
                                        <span class="list-detail__type list-detail__type--<?= Security::escape($itemType) ?>">
                                            <?= Security::escape($itemType === 'tv' ? 'TV' : 'Movie') ?>
                                        </span>
                                    </div>
                                    <h3 class="list-detail__item-title"><?= Security::escape($itemTitle) ?></h3>
                                </a>
                                <form method="POST" action="" class="list-detail__remove-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="remove_from_list">
                                    <input type="hidden" name="list_id" value="<?= (int) $selectedList['id'] ?>">
                                    <input type="hidden" name="tmdb_id" value="<?= $itemTmdbId ?>">
                                    <input type="hidden" name="media_type" value="<?= Security::escape($itemType) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </div>
</main>

<!-- Rename list modal -->
<div class="modal list-modal" id="rename-list-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="rename-list-title">
    <div class="modal__backdrop" data-close-modal="rename"></div>
    <div class="modal__dialog list-modal__dialog">
        <div class="modal__header">
            <h2 class="modal__title" id="rename-list-title">Rename List</h2>
            <button type="button" class="modal__close" data-close-modal="rename" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="" class="list-modal__body">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="rename_list">
            <input type="hidden" name="list_id" id="rename-list-id" value="">
            <label class="form-group__label" for="rename-list-name">List name</label>
            <input type="text"
                   id="rename-list-name"
                   name="name"
                   class="form-group__input"
                   maxlength="100"
                   required>
            <div class="list-modal__footer">
                <button type="button" class="btn btn--secondary" data-close-modal="rename">Cancel</button>
                <button type="submit" class="btn btn--primary">Save Name</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= Security::escape(BASE_URL) ?>/js/lists.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>

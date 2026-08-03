<?php

declare(strict_types=1);

/**
 * Cinomnia Details Page
 *
 * Immersive title view with trailer modal, watchlist, TV seasons accordion,
 * and movie collection/franchise grid.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

$mediaType = getParam('type', 'movie');
$mediaType = in_array($mediaType, ['movie', 'tv'], true) ? $mediaType : 'movie';
$id        = (int) getParam('id', '0');

if ($id <= 0) {
    redirect('/index.php');
}

$detailsPath = '/details.php?type=' . urlencode($mediaType) . '&id=' . $id;
$userRating  = null;
$isWatched   = false;
$userLists   = [];
$listIdsWithItem = [];
$userPanelConfig = null;

// --- Fetch TMDB data ---
try {
    $details = $mediaType === 'tv'
        ? $tmdb->getTvDetails($id)
        : $tmdb->getMovieDetails($id);
} catch (Throwable) {
    $details = null;
}

if ($details === null) {
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="detail-page">
        <div class="detail-content">
            <div class="alert alert--error" role="alert">The requested title could not be found.</div>
            <a href="<?= Security::escape(BASE_URL) ?>/index.php" class="btn btn--secondary">Back to Home</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Extract fields ---
$title       = $details['title'] ?? $details['name'] ?? 'Unknown';
$pageTitle   = $title;
$overview    = $details['overview'] ?? 'No synopsis available.';
$rating      = $tmdb->formatRating($details['vote_average'] ?? null);
$voteCount   = (int) ($details['vote_count'] ?? 0);
$posterPath  = $details['poster_path'] ?? null;
$poster      = $tmdb->posterUrl($posterPath, TMDB_POSTER_SIZE);
$backdrop    = $tmdb->backdropUrl($details['backdrop_path'] ?? null, 'original');
$releaseDate = $tmdb->formatDate($details);
$typeLabel   = $mediaType === 'tv' ? 'TV Show' : 'Movie';
$trailerKey  = $tmdb->getYoutubeTrailerKey($details);

if ($mediaType === 'movie') {
    $runtime = $tmdb->formatRuntime($details['runtime'] ?? null);
} else {
    $epRuntime = $details['episode_run_time'][0] ?? null;
    $runtime   = $epRuntime ? $tmdb->formatRuntime((int) $epRuntime) . ' / ep' : 'N/A';
    $seasons   = (int) ($details['number_of_seasons'] ?? 0);
    $episodes  = (int) ($details['number_of_episodes'] ?? 0);
}

$genres = array_column($details['genres'] ?? [], 'name');
$cast   = array_slice($details['credits']['cast'] ?? [], 0, 12);

// User interaction state for logged-in users
if ($auth->isLoggedIn()) {
    $userId          = (int) $auth->getUserId();
    $userLists       = $customLists->getSelectableListsForUser($userId);
    $listIdsWithItem = $customLists->getListIdsContainingItem($userId, $id, $mediaType);
    $interaction     = $userMedia->getInteraction($userId, $id, $mediaType);

    if ($interaction !== null) {
        $userRating = $interaction['rating'];
        $isWatched  = $interaction['is_watched'];
    }

    $csrfToken = Security::generateCsrfToken();

    $userPanelConfig = [
        'apiUrl'          => BASE_URL . '/user-actions.php',
        'tmdbId'          => $id,
        'mediaType'       => $mediaType,
        'title'           => $title,
        'posterPath'      => $posterPath,
        'userRating'      => $userRating,
        'isWatched'       => $isWatched,
        'lists'           => $userLists,
        'listIdsWithItem' => $listIdsWithItem,
    ];
}

// TV seasons with episodes (parallel TMDB requests)
$tvSeasons = [];
if ($mediaType === 'tv') {
    $tvSeasons = $tmdb->getTvSeasonsWithEpisodes($id, $details['seasons'] ?? []);
}

// Movie collection / franchise
$collection      = null;
$collectionParts = [];
if ($mediaType === 'movie' && !empty($details['belongs_to_collection']['id'])) {
    $collectionId = (int) $details['belongs_to_collection']['id'];
    $collection   = $tmdb->getCollection($collectionId);

    if ($collection !== null) {
        $collectionParts = array_values(array_filter(
            $collection['parts'] ?? [],
            static fn(array $part): bool => (int) ($part['id'] ?? 0) !== $id
        ));
        usort($collectionParts, static fn(array $a, array $b): int =>
            strcmp($a['release_date'] ?? '', $b['release_date'] ?? ''));
    }
}

// Flash message from login redirect
$loginFlash = getParam('msg') === 'login_required'
    ? 'Please log in to save ratings, watched status, or custom lists.'
    : '';

$detailPageConfig = [
    'tmdbId'         => $id,
    'mediaType'      => $mediaType,
    'commentsApiUrl' => BASE_URL . '/comments-api.php',
    'isLoggedIn'     => $auth->isLoggedIn(),
    'username'       => $auth->getUsername(),
    'loginUrl'       => BASE_URL . '/login.php?redirect=' . urlencode($detailsPath),
];

require __DIR__ . '/includes/header.php';
?>

<main class="detail-page">
    <!-- Immersive hero with backdrop, poster, meta & CTAs -->
    <section class="detail-hero"<?= $backdrop !== null ? ' style="background-image:url(\'' . Security::escape($backdrop) . '\')"' : '' ?>>
        <div class="detail-hero__scrim"></div>
        <div class="detail-hero__inner">
            <a href="<?= Security::escape(BASE_URL) ?>/index.php?type=<?= Security::escape($mediaType) ?>"
               class="detail-hero__back">Back to Browse</a>

            <div class="detail-hero__layout">
                <div class="detail-hero__poster-wrap">
                    <img src="<?= Security::escape($poster) ?>" alt="" class="detail-hero__poster" width="500" height="750">
                </div>

                <div class="detail-hero__info">
                    <span class="detail-hero__type detail-hero__type--<?= Security::escape($mediaType) ?>">
                        <?= Security::escape($typeLabel) ?>
                    </span>
                    <h1 class="detail-hero__title"><?= Security::escape($title) ?></h1>

                    <div class="detail-hero__meta">
                        <span class="detail-hero__rating"><?= Security::escape($rating) ?> <small>/ 10</small></span>
                        <span class="detail-hero__meta-item"><?= Security::escape($releaseDate) ?></span>
                        <span class="detail-hero__meta-item"><?= Security::escape($runtime) ?></span>
                        <?php if ($mediaType === 'tv' && $seasons > 0): ?>
                            <span class="detail-hero__meta-item">
                                <?= $seasons ?> Season<?= $seasons === 1 ? '' : 's' ?>
                                &middot; <?= $episodes ?> Episodes
                            </span>
                        <?php endif; ?>
                        <span class="detail-hero__meta-item detail-hero__meta-item--muted">
                            <?= number_format($voteCount) ?> votes
                        </span>
                    </div>

                    <?php if (!empty($genres)): ?>
                        <div class="detail-hero__genres">
                            <?php foreach ($genres as $genreName): ?>
                                <span class="detail-hero__genre"><?= Security::escape($genreName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="detail-hero__actions">
                        <?php if ($trailerKey !== null): ?>
                            <button type="button"
                                    class="btn btn--accent btn--lg"
                                    id="play-trailer-btn"
                                    data-trailer-key="<?= Security::escape($trailerKey) ?>">
                                Play Trailer
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn--accent btn--lg" disabled>
                                Trailer Unavailable
                            </button>
                        <?php endif; ?>

                        <?php if ($auth->isLoggedIn() && $userPanelConfig !== null): ?>
                            <input type="hidden" id="detail-csrf-token" value="<?= Security::escape($csrfToken) ?>">
                            <div class="detail-user-panel" id="detail-user-panel">
                                <div class="detail-user-panel__row">
                                    <button type="button"
                                            id="watched-toggle-btn"
                                            class="detail-user-panel__watched-btn<?= $isWatched ? ' is-watched' : '' ?>"
                                            aria-pressed="<?= $isWatched ? 'true' : 'false' ?>">
                                        <span class="detail-user-panel__watched-icon" aria-hidden="true">
                                            <?= $isWatched ? '&#10003;' : '&#9675;' ?>
                                        </span>
                                        <span class="detail-user-panel__watched-label">
                                            <?= $isWatched ? 'Watched' : 'Mark as Watched' ?>
                                        </span>
                                    </button>

                                    <button type="button"
                                            id="add-to-list-btn"
                                            class="btn btn--secondary btn--lg detail-user-panel__list-btn">
                                        Add to List
                                    </button>
                                </div>

                                <div class="detail-user-panel__rating" id="user-rating-widget">
                                    <div class="detail-user-panel__rating-header">
                                        <span class="detail-user-panel__rating-title">Your Rating</span>
                                        <span class="detail-user-panel__rating-value" id="user-rating-value">
                                            <?= $userRating !== null ? $userRating . ' / 10' : 'Not rated' ?>
                                        </span>
                                    </div>
                                    <div class="star-rating"
                                         id="star-rating"
                                         role="slider"
                                         aria-label="Rate this title from 1 to 10"
                                         aria-valuemin="1"
                                         aria-valuemax="10"
                                         aria-valuenow="<?= $userRating ?? 0 ?>"
                                         tabindex="0">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <button type="button"
                                                    class="star-rating__star<?= $userRating !== null && $i <= $userRating ? ' is-active' : '' ?>"
                                                    data-value="<?= $i ?>"
                                                    aria-label="Rate <?= $i ?> out of 10">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                </svg>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="detail-user-panel__rating-hint" id="rating-feedback" role="status" aria-live="polite"></p>
                                </div>

                                <p class="detail-user-panel__toast" id="user-panel-toast" role="status" aria-live="polite" hidden></p>
                            </div>
                            <script type="application/json" id="detail-user-config"><?=
                                json_encode(
                                    $userPanelConfig,
                                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                                )
                            ?></script>
                        <?php else: ?>
                            <a href="<?= Security::escape(BASE_URL) ?>/login.php?redirect=<?= urlencode($detailsPath) ?>"
                               class="btn btn--secondary btn--lg">
                                Log in to track this title
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($loginFlash !== ''): ?>
                        <p class="detail-hero__flash" role="status"><?= Security::escape($loginFlash) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content sections -->
    <div class="detail-content">
        <section class="detail-block">
            <h2 class="detail-block__title">Synopsis</h2>
            <p class="detail-block__text"><?= Security::escape($overview) ?></p>
        </section>

        <?php if (!empty($cast)): ?>
            <section class="detail-block">
                <h2 class="detail-block__title">Cast</h2>
                <div class="cast-grid">
                    <?php foreach ($cast as $member): ?>
                        <div class="cast-member">
                            <?php if (!empty($member['profile_path'])): ?>
                                <img src="<?= Security::escape($tmdb->posterUrl($member['profile_path'], 'w185')) ?>"
                                     alt="" class="cast-member__photo" loading="lazy" width="185" height="278">
                            <?php else: ?>
                                <div class="cast-member__placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <span class="cast-member__name"><?= Security::escape($member['name'] ?? '') ?></span>
                            <span class="cast-member__role"><?= Security::escape($member['character'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($mediaType === 'tv' && !empty($tvSeasons)): ?>
            <section class="detail-block">
                <h2 class="detail-block__title">Seasons &amp; Episodes</h2>
                <div class="season-accordion">
                    <?php foreach ($tvSeasons as $index => $season): ?>
                        <details class="season-accordion__item"<?= $index === 0 ? ' open' : '' ?>>
                            <summary class="season-accordion__summary">
                                <span class="season-accordion__label">
                                    Season <?= (int) $season['season_number'] ?>
                                    <?php if (($season['name'] ?? '') !== 'Season ' . $season['season_number']): ?>
                                        — <?= Security::escape($season['name']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="season-accordion__count">
                                    <?= count($season['episodes']) ?> episode<?= count($season['episodes']) === 1 ? '' : 's' ?>
                                </span>
                            </summary>
                            <ul class="episode-list">
                                <?php foreach ($season['episodes'] as $episode): ?>
                                    <li class="episode-list__item">
                                        <span class="episode-list__num">
                                            E<?= (int) ($episode['episode_number'] ?? 0) ?>
                                        </span>
                                        <div class="episode-list__body">
                                            <span class="episode-list__title">
                                                <?= Security::escape($episode['name'] ?? 'Untitled') ?>
                                            </span>
                                            <span class="episode-list__date">
                                                <?= Security::escape($tmdb->formatDate($episode['air_date'] ?? '')) ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($mediaType === 'movie' && !empty($collectionParts)): ?>
            <section class="detail-block">
                <h2 class="detail-block__title">
                    Part of the Collection
                    <?php if (!empty($collection['name'])): ?>
                        <span class="detail-block__subtitle"><?= Security::escape($collection['name']) ?></span>
                    <?php endif; ?>
                </h2>
                <div class="collection-grid">
                    <?php foreach ($collectionParts as $part): ?>
                        <?php
                        $partId    = (int) ($part['id'] ?? 0);
                        $partTitle = $tmdb->getTitle($part);
                        $partPoster = $tmdb->posterUrl($part['poster_path'] ?? null);
                        ?>
                        <a href="<?= Security::escape(BASE_URL) ?>/details.php?type=movie&amp;id=<?= $partId ?>"
                           class="collection-card">
                            <div class="collection-card__poster-wrap">
                                <img src="<?= Security::escape($partPoster) ?>" alt="" class="collection-card__poster" loading="lazy">
                            </div>
                            <div class="collection-card__info">
                                <h3 class="collection-card__title"><?= Security::escape($partTitle) ?></h3>
                                <?php if (!empty($part['release_date'])): ?>
                                    <span class="collection-card__year"><?= Security::escape(substr($part['release_date'], 0, 4)) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Comments -->
    <section class="detail-comments" id="detail-comments" aria-labelledby="detail-comments-title">
        <div class="detail-comments__inner">
            <header class="detail-comments__header">
                <h2 class="detail-comments__title" id="detail-comments-title">Discussion</h2>
                <p class="detail-comments__subtitle">Share your thoughts on <?= Security::escape($title) ?></p>
            </header>

            <?php if ($auth->isLoggedIn()): ?>
                <form id="comment-form" class="detail-comments__form">
                    <label class="detail-comments__form-label" for="comment-body">Your comment</label>
                    <textarea id="comment-body"
                              class="detail-comments__textarea"
                              rows="3"
                              maxlength="2000"
                              placeholder="What did you think of this title?"
                              required></textarea>
                    <div class="detail-comments__form-footer">
                        <span class="detail-comments__char-count" id="comment-char-count">0 / 2000</span>
                        <button type="submit" class="btn btn--accent btn--sm" id="comment-submit-btn">
                            Post Comment
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="detail-comments__login-prompt">
                    <a href="<?= Security::escape(BASE_URL) ?>/login.php?redirect=<?= urlencode($detailsPath) ?>"
                       class="btn btn--secondary btn--sm">
                        Log in to join the discussion
                    </a>
                </div>
            <?php endif; ?>

            <p class="detail-comments__feedback" id="comments-feedback" role="status" aria-live="polite" hidden></p>

            <div class="detail-comments__loading" id="comments-loading">Loading comments…</div>
            <ul class="detail-comments__list" id="comments-list" hidden></ul>
            <p class="detail-comments__empty" id="comments-empty" hidden>No comments yet. Be the first to share your thoughts!</p>
        </div>
    </section>
</main>

<script type="application/json" id="detail-page-config"><?=
    json_encode($detailPageConfig, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
?></script>

<!-- Trailer modal -->
<div class="modal" id="trailer-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="trailer-modal-title">
    <div class="modal__backdrop" data-close-modal="trailer"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h2 class="modal__title" id="trailer-modal-title">Trailer — <?= Security::escape($title) ?></h2>
            <button type="button" class="modal__close" data-close-modal="trailer" aria-label="Close trailer">&times;</button>
        </div>
        <div class="modal__body">
            <div class="modal__video-wrap">
                <iframe id="trailer-iframe"
                        title="<?= Security::escape($title) ?> trailer"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->isLoggedIn()): ?>
<!-- Add to List modal -->
<div class="modal list-modal" id="list-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="list-modal-title">
    <div class="modal__backdrop" data-close-modal="list"></div>
    <div class="modal__dialog list-modal__dialog">
        <div class="modal__header">
            <h2 class="modal__title" id="list-modal-title">Add to List</h2>
            <button type="button" class="modal__close" data-close-modal="list" aria-label="Close">&times;</button>
        </div>
        <div class="modal__body list-modal__body">
            <div id="list-modal-empty" class="list-modal__empty" hidden>
                <div class="list-modal__empty-icon" aria-hidden="true">+</div>
                <h3 class="list-modal__empty-title">Create your first list</h3>
                <p class="list-modal__empty-text">Organize titles into custom collections like &ldquo;Weekend Watchlist&rdquo; or &ldquo;Top Sci-Fi&rdquo;.</p>
                <form id="list-modal-create-first-form" class="list-modal__form">
                    <input type="text"
                           id="list-modal-first-name"
                           class="form-group__input"
                           placeholder="List name"
                           maxlength="100"
                           required>
                    <button type="submit" class="btn btn--primary btn--full">Create &amp; Add Title</button>
                </form>
            </div>

            <div id="list-modal-existing" class="list-modal__existing" hidden>
                <p class="list-modal__hint">Select one or more lists for this title.</p>
                <ul id="list-modal-checklist" class="list-modal__checklist"></ul>

                <details class="list-modal__create-new">
                    <summary class="list-modal__create-new-summary">+ Create a New List</summary>
                    <form id="list-modal-create-form" class="list-modal__form">
                        <input type="text"
                               id="list-modal-new-name"
                               class="form-group__input"
                               placeholder="New list name"
                               maxlength="100"
                               required>
                        <button type="submit" class="btn btn--secondary">Create &amp; Add</button>
                    </form>
                </details>

                <div class="list-modal__footer">
                    <button type="button" class="btn btn--secondary" data-close-modal="list">Cancel</button>
                    <button type="button" class="btn btn--primary" id="list-modal-save-btn">Add to Selected</button>
                </div>
            </div>

            <p class="list-modal__feedback" id="list-modal-feedback" role="status" aria-live="polite" hidden></p>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?= Security::escape(BASE_URL) ?>/js/details.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>

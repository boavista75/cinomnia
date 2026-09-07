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
$isWantToWatch = false;
$userLists   = [];
$listIdsWithItem = [];
$userPanelConfig = null;
$noteBody    = '';
$noteUpdated = null;
$pageTitle   = $mediaType === 'tv' ? 'TV Show' : 'Movie';

require __DIR__ . '/includes/header.php';

// --- Fetch TMDB data ---
try {
    $details = $mediaType === 'tv'
        ? $tmdb->getTvDetails($id)
        : $tmdb->getMovieDetails($id);
} catch (Throwable) {
    $details = null;
}

if ($details === null) {
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

    $isWantToWatch = $customLists->isItemInWantToWatchList($userId, $id, $mediaType);

    $note           = $notes->getNote($id, $mediaType);
    $noteBody       = $note['body'];
    $noteUpdated    = $note['updated_at'];

    $scoreWord = 'Not rated';
    $scoreTone = 'empty';

    if ($userRating !== null) {
        if ($userRating <= 3) {
            $scoreWord = 'Poor';
            $scoreTone = 'low';
        } elseif ($userRating <= 5) {
            $scoreWord = 'Okay';
            $scoreTone = 'mid';
        } elseif ($userRating <= 7) {
            $scoreWord = 'Good';
            $scoreTone = 'good';
        } elseif ($userRating <= 9) {
            $scoreWord = 'Great';
            $scoreTone = 'great';
        } else {
            $scoreWord = 'Excellent';
            $scoreTone = 'top';
        }
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
        'isWantToWatch'   => $isWantToWatch,
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
    'tmdbId'       => $id,
    'mediaType'    => $mediaType,
    'notesApiUrl'  => BASE_URL . '/user-actions.php',
    'noteMaxChars' => \Cinomnia\Auth\NoteService::MAX_BODY_LENGTH,
];
?>

<main class="detail-page">
    <script>document.title = <?= json_encode($title . ' | ' . APP_NAME, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;</script>
    <?php if ($auth->isLoggedIn() && isset($csrfToken)): ?>
        <input type="hidden" id="detail-csrf-token" value="<?= Security::escape($csrfToken) ?>">
    <?php endif; ?>
    <!-- Immersive hero with backdrop, poster, meta & library dock -->
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

                    <ul class="detail-hero__facts">
                        <li class="detail-hero__facts-score">
                            <strong><?= Security::escape($rating) ?></strong>
                            <span>TMDB</span>
                        </li>
                        <li><?= Security::escape($releaseDate) ?></li>
                        <li><?= Security::escape($runtime) ?></li>
                        <?php if ($mediaType === 'tv' && $seasons > 0): ?>
                            <li>
                                <?= $seasons ?> season<?= $seasons === 1 ? '' : 's' ?>
                                · <?= $episodes ?> episodes
                            </li>
                        <?php endif; ?>
                        <li><?= number_format($voteCount) ?> votes</li>
                    </ul>

                    <?php if (!empty($genres)): ?>
                        <p class="detail-hero__genres">
                            <?= Security::escape(implode(' · ', $genres)) ?>
                        </p>
                    <?php endif; ?>

                    <div class="detail-hero__cta">
                        <?php if ($trailerKey !== null): ?>
                            <button type="button"
                                    class="btn btn--accent btn--lg detail-hero__trailer"
                                    id="play-trailer-btn"
                                    data-trailer-key="<?= Security::escape($trailerKey) ?>">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                    <path fill="currentColor" d="M8 5.14v13.72L19.5 12 8 5.14z"/>
                                </svg>
                                Play Trailer
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn--accent btn--lg detail-hero__trailer" disabled>
                                Trailer Unavailable
                            </button>
                        <?php endif; ?>

                        <?php if (!$auth->isLoggedIn()): ?>
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

                <?php if ($auth->isLoggedIn() && $userPanelConfig !== null): ?>
                    <div class="detail-dock" id="detail-user-panel">
                        <div class="detail-dock__library">
                            <p class="detail-dock__label">Library</p>
                            <div class="detail-dock__status" role="group" aria-label="Watch status">
                                <button type="button"
                                        id="want-to-watch-btn"
                                        class="detail-dock__chip<?= $isWantToWatch ? ' is-active is-want' : '' ?>"
                                        aria-pressed="<?= $isWantToWatch ? 'true' : 'false' ?>">
                                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                        <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"
                                              d="M6.5 4.5h11a1 1 0 0 1 1 1v14l-6.5-3.4-6.5 3.4v-14a1 1 0 0 1 1-1z"/>
                                    </svg>
                                    Want to Watch
                                </button>
                                <button type="button"
                                        id="watched-toggle-btn"
                                        class="detail-dock__chip<?= $isWatched ? ' is-active is-watched' : '' ?>"
                                        aria-pressed="<?= $isWatched ? 'true' : 'false' ?>">
                                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                        <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                              d="M8.2 12.3l2.4 2.4 5.2-5.4"/>
                                    </svg>
                                    Watched
                                </button>
                            </div>
                            <button type="button" id="add-to-list-btn" class="detail-dock__list-link">
                                Add to a list
                            </button>
                        </div>

                        <div class="detail-dock__score" id="user-rating-widget">
                            <p class="detail-dock__label">Your score</p>
                            <div class="detail-score">
                                <div class="detail-score__display detail-score__display--<?= Security::escape($scoreTone) ?>">
                                    <span class="detail-score__number" id="user-rating-value">
                                        <?= $userRating !== null ? (int) $userRating : '—' ?>
                                    </span>
                                    <span class="detail-score__suffix">/10</span>
                                    <span class="detail-score__word" id="user-rating-word"><?= Security::escape($scoreWord) ?></span>
                                </div>
                                <div class="score-picker"
                                     id="score-picker"
                                     role="radiogroup"
                                     aria-label="Rate this title from 1 to 10">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <button type="button"
                                                class="score-picker__btn<?= $userRating === $i ? ' is-active' : '' ?>"
                                                data-value="<?= $i ?>"
                                                role="radio"
                                                aria-checked="<?= $userRating === $i ? 'true' : 'false' ?>"
                                                aria-label="Rate <?= $i ?> out of 10">
                                            <?= $i ?>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <p class="detail-dock__toast" id="user-panel-toast" role="status" aria-live="polite" hidden></p>
                    </div>
                    <script type="application/json" id="detail-user-config"><?=
                        json_encode(
                            $userPanelConfig,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                        )
                    ?></script>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Main content sections -->
    <div class="detail-content">
        <div class="detail-content__grid">
        <section class="detail-block">
            <h2 class="detail-block__title">Synopsis</h2>
            <p class="detail-block__text"><?= Security::escape($overview) ?></p>
        </section>

        <?php if (!empty($cast)): ?>
            <section class="detail-block detail-block--cast">
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

        <?php if ($auth->isLoggedIn()): ?>
            <?php $hasNote = $noteBody !== ''; ?>
            <section class="detail-block detail-notes" id="detail-notes" aria-labelledby="detail-notes-title">
                <h2 class="detail-block__title" id="detail-notes-title">Notes</h2>
                <p class="detail-notes__lede">Your private impressions of <?= Security::escape($title) ?></p>

                <div class="detail-notes__card" id="note-view"<?= $hasNote ? '' : ' hidden' ?>>
                    <p class="detail-notes__body" id="note-display"><?= Security::escape($noteBody) ?></p>
                    <div class="detail-notes__toolbar">
                        <span class="detail-notes__meta" id="note-saved-at">
                            <?php if ($noteUpdated !== null && $noteUpdated !== ''): ?>
                                Last saved <?= Security::escape($noteUpdated) ?>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="btn btn--secondary btn--sm" id="note-edit-btn">
                            Edit notes
                        </button>
                    </div>
                </div>

                <form id="note-form" class="detail-notes__card detail-notes__form"<?= $hasNote ? ' hidden' : '' ?>>
                    <label class="form-group__label" for="note-body">Your notes</label>
                    <textarea id="note-body"
                              class="form-group__input detail-notes__textarea"
                              rows="6"
                              maxlength="<?= (int) \Cinomnia\Auth\NoteService::MAX_BODY_LENGTH ?>"
                              placeholder="Write what you thought of this title…"><?= Security::escape($noteBody) ?></textarea>
                    <div class="detail-notes__toolbar">
                        <span class="detail-notes__meta" id="note-char-count">
                            <?= mb_strlen($noteBody) ?> / <?= (int) \Cinomnia\Auth\NoteService::MAX_BODY_LENGTH ?>
                        </span>
                        <div class="detail-notes__actions">
                            <button type="button"
                                    class="btn btn--secondary btn--sm"
                                    id="note-cancel-btn"
                                    <?= $hasNote ? '' : ' hidden' ?>>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn--primary btn--sm" id="note-submit-btn">
                                Save notes
                            </button>
                        </div>
                    </div>
                </form>

                <p class="detail-notes__feedback" id="note-feedback" role="status" aria-live="polite" hidden></p>
            </section>
        <?php endif; ?>
        </div>
    </div>
</main>

<script type="application/json" id="detail-page-config"><?=
    json_encode($detailPageConfig, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
?></script>

<!-- Trailer modal -->
<div class="modal modal--video" id="trailer-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="trailer-modal-title">
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

<script src="<?= Security::escape(BASE_URL) ?>/js/details.js?v=<?= (int) filemtime(APP_ROOT . '/js/details.js') ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>

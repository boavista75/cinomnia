<?php

declare(strict_types=1);

/**
 * Cinomnia Home Page
 *
 * Displays a responsive grid of movies/TV shows fetched from TMDB.
 * Sidebar filters: search, media type, sort, release year, rating, genres.
 */

require_once __DIR__ . '/includes/bootstrap.php';

use Cinomnia\Security\Security;

// --- Parse and sanitise query parameters ---
$mediaType = getParam('type', 'movie');
$mediaType = in_array($mediaType, ['movie', 'tv'], true) ? $mediaType : 'movie';

$search  = getParam('q', '');
$page    = max(1, (int) getParam('page', '1'));
$filters = parseBrowseFilters();

$sort      = $filters['sort'];
$genreId   = $filters['genre_id'];
$yearFrom  = $filters['year_from'];
$yearTo    = $filters['year_to'];
$rating    = $filters['rating'];

$currentYear = (int) date('Y');
$yearOptions = range($currentYear, 1950);

/** Base query params reused in sidebar links and pagination */
$baseQuery = [
    'type'      => $mediaType,
    'sort'      => $sort,
    'genre'     => $genreId,
    'year_from' => $yearFrom,
    'year_to'   => $yearTo,
    'rating'    => $rating,
    'q'         => $search,
];

$errorMessage = null;
$results      = [];

try {
    if ($search !== '') {
        // Search uses multi-search; other filters are not applied by TMDB search API
        $results = $tmdb->search($search, $page);
    } elseif (!$tmdb->requiresDiscoverEndpoint($filters)) {
        // Pure "Trending" with no extra filters → dedicated trending endpoint
        $results = $tmdb->getTrending($mediaType, $page);
    } else {
        // Combined sort / year / rating / genre → discover API
        $results = $tmdb->discover($mediaType, $filters, $page);
    }

    // List endpoints omit runtime / season / episode counts — fetch them in
    // parallel and append to each result so cards can show full metadata.
    $results = $tmdb->enrichWithDetails($results, $mediaType);

    $genres = $tmdb->getGenres($mediaType);
} catch (Throwable $e) {
    $errorMessage = 'Unable to load content from TMDB. Please check your internet connection.';
    $genres       = [];
}

// Human-readable labels for the results heading
$sortLabels = [
    'trending' => 'Trending',
    'highest'  => 'Highest Rated',
    'lowest'   => 'Lowest Rated',
];

$ratingLabels = [
    'below5' => 'Below 5.0',
    'above5' => 'Above 5.0',
    'above6' => 'Above 6.0',
    'above7' => 'Above 7.0',
    'above8' => 'Above 8.0',
    'above9' => 'Above 9.0',
];

$pageTitle = $search !== ''
    ? 'Search: ' . $search
    : ($mediaType === 'tv' ? 'TV Shows' : 'Movies');

require __DIR__ . '/includes/header.php';
?>

<main class="layout">
    <!-- ==================================================================== -->
    <!-- Sidebar: a SINGLE filter form. The TMDB request is only triggered     -->
    <!-- when the "Apply Filters" button at the bottom is clicked (form submit) -->
    <!-- ==================================================================== -->
    <aside class="sidebar" aria-label="Filters">
        <form class="filters" method="GET" action="<?= Security::escape(BASE_URL) ?>/index.php">
            <div class="filters__head">
                <h2 class="filters__title">Filters</h2>
                <a href="<?= Security::escape(BASE_URL) ?>/index.php" class="filters__reset-link">Reset</a>
            </div>

            <!-- Search -->
            <div class="filters__group">
                <label class="filters__label" for="q">Search</label>
                <div class="filters__search">
                    <input
                        type="search"
                        id="q"
                        name="q"
                        class="filters__search-input"
                        placeholder="Search titles…"
                        value="<?= Security::escape($search) ?>"
                        aria-label="Search movies and TV shows"
                    >
                </div>
            </div>

            <!-- Media Type (segmented control) -->
            <div class="filters__group">
                <span class="filters__label">Media Type</span>
                <div class="segmented" role="radiogroup" aria-label="Media type">
                    <label class="segmented__option">
                        <input type="radio" name="type" value="movie"<?= $mediaType === 'movie' ? ' checked' : '' ?>>
                        <span class="segmented__pill">Movies</span>
                    </label>
                    <label class="segmented__option">
                        <input type="radio" name="type" value="tv"<?= $mediaType === 'tv' ? ' checked' : '' ?>>
                        <span class="segmented__pill">TV Shows</span>
                    </label>
                </div>
            </div>

            <!-- Sort By (chip group) -->
            <div class="filters__group">
                <span class="filters__label">Sort By</span>
                <div class="chip-group" role="radiogroup" aria-label="Sort by">
                    <?php foreach ($sortLabels as $sortKey => $sortLabel): ?>
                        <label class="chip">
                            <input type="radio" name="sort" value="<?= Security::escape($sortKey) ?>"<?= $sort === $sortKey ? ' checked' : '' ?>>
                            <span class="chip__label"><?= Security::escape($sortLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Genre (select) -->
            <div class="filters__group">
                <label class="filters__label" for="genre">Genre</label>
                <div class="select-wrap">
                    <select id="genre" name="genre" class="select">
                        <option value="0">All Genres</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?= (int) $genre['id'] ?>"<?= $genreId === (int) $genre['id'] ? ' selected' : '' ?>>
                                <?= Security::escape($genre['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Release Year (From – To) -->
            <div class="filters__group">
                <span class="filters__label">Release Year</span>
                <div class="filters__range">
                    <div class="select-wrap">
                        <select name="year_from" class="select" aria-label="Year from">
                            <option value="">From</option>
                            <?php foreach ($yearOptions as $year): ?>
                                <option value="<?= $year ?>"<?= $yearFrom === $year ? ' selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span class="filters__range-sep" aria-hidden="true">–</span>
                    <div class="select-wrap">
                        <select name="year_to" class="select" aria-label="Year to">
                            <option value="">To</option>
                            <?php foreach ($yearOptions as $year): ?>
                                <option value="<?= $year ?>"<?= $yearTo === $year ? ' selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Rating (select) -->
            <div class="filters__group">
                <label class="filters__label" for="rating">Rating</label>
                <div class="select-wrap">
                    <select id="rating" name="rating" class="select">
                        <option value="">Any rating</option>
                        <?php foreach ($ratingLabels as $ratingKey => $ratingLabel): ?>
                            <option value="<?= Security::escape($ratingKey) ?>"<?= $rating === $ratingKey ? ' selected' : '' ?>>
                                <?= Security::escape($ratingLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Sticky action bar with the single Apply Filters button -->
            <div class="filters__actions">
                <button type="submit" class="btn btn--primary btn--full btn--lg">
                    Apply Filters
                </button>
            </div>
        </form>
    </aside>

    <!-- Main Content -->
    <section class="content" aria-label="Results">
        <div class="content__header">
            <h1 class="content__title">
                <?php if ($search !== ''): ?>
                    Results for "<?= Security::escape($search) ?>"
                <?php else: ?>
                    <?= Security::escape($sortLabels[$sort] ?? 'Trending') ?>
                    <?= Security::escape($mediaType === 'tv' ? 'TV Shows' : 'Movies') ?>
                    <?php if ($genreId > 0): ?>
                        — <?= Security::escape(
                            array_values(array_filter($genres, fn($g) => (int) $g['id'] === $genreId))[0]['name'] ?? 'Genre'
                        ) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>
            <span class="content__count"><?= count($results) ?> titles</span>
        </div>

        <?php if ($search === '' && ($yearFrom > 0 || $yearTo > 0 || $rating !== '')): ?>
            <div class="active-filters" aria-label="Active filters">
                <?php if ($yearFrom > 0 || $yearTo > 0): ?>
                    <span class="active-filters__tag">
                        Year:
                        <?= $yearFrom > 0 ? Security::escape((string) $yearFrom) : 'Any' ?>
                        –
                        <?= $yearTo > 0 ? Security::escape((string) $yearTo) : 'Any' ?>
                    </span>
                <?php endif; ?>
                <?php if ($rating !== ''): ?>
                    <span class="active-filters__tag">
                        Rating: <?= Security::escape($ratingLabels[$rating] ?? $rating) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== null): ?>
            <div class="alert alert--error" role="alert">
                <?= Security::escape($errorMessage) ?>
            </div>
        <?php elseif (empty($results)): ?>
            <div class="empty-state">
                <p>No results found. Try a different search or filter.</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($results as $item): ?>
                    <?php
                    $itemType    = $tmdb->getMediaType($item, $mediaType);
                    $typeLabel   = $tmdb->getMediaTypeLabel($item, $mediaType);
                    $itemId      = (int) ($item['id'] ?? 0);
                    $title       = $tmdb->getTitle($item);
                    $itemRating  = $tmdb->formatRating($item['vote_average'] ?? null);
                    $poster      = $tmdb->posterUrl($item['poster_path'] ?? null);

                    // Metadata enriched via parallel TMDB detail requests
                    $releaseDate = $tmdb->formatDate($item);
                    $runtime     = $tmdb->formatRuntime($item['runtime'] ?? null);
                    $seasons     = $tmdb->formatSeasonCount(
                        isset($item['number_of_seasons']) ? (int) $item['number_of_seasons'] : null
                    );
                    $episodes    = $tmdb->formatEpisodeCount(
                        isset($item['number_of_episodes']) ? (int) $item['number_of_episodes'] : null
                    );
                    $overview    = $tmdb->truncateOverview($item['overview'] ?? null);
                    ?>
                    <article class="card">
                        <a href="<?= Security::escape(BASE_URL) ?>/details.php?type=<?= Security::escape($itemType) ?>&amp;id=<?= $itemId ?>"
                           class="card__link">
                            <div class="card__media">
                                <img
                                    src="<?= Security::escape($poster) ?>"
                                    alt=""
                                    class="card__poster"
                                    loading="lazy"
                                    width="342"
                                    height="513"
                                >
                                <span class="card__badge card__badge--<?= Security::escape($itemType) ?>">
                                    <?= Security::escape($typeLabel) ?>
                                </span>
                            </div>

                            <div class="card__body">
                                <div class="card__header">
                                    <h2 class="card__title"><?= Security::escape($title) ?></h2>
                                    <div class="card__rating" aria-label="Rating: <?= Security::escape($itemRating) ?> out of 10">
                                        <span class="card__rating-value"><?= Security::escape($itemRating) ?></span>
                                        <span class="card__rating-scale">/ 10</span>
                                    </div>
                                </div>

                                <p class="card__date"><?= Security::escape($releaseDate) ?></p>

                                <p class="card__overview"><?= Security::escape($overview) ?></p>

                                <?php if ($itemType === 'tv'): ?>
                                    <dl class="card__stats">
                                        <div class="card__stat">
                                            <dt>Seasons</dt>
                                            <dd><?= Security::escape($seasons) ?></dd>
                                        </div>
                                        <div class="card__stat">
                                            <dt>Episodes</dt>
                                            <dd><?= Security::escape($episodes) ?></dd>
                                        </div>
                                    </dl>
                                <?php else: ?>
                                    <dl class="card__stats">
                                        <div class="card__stat">
                                            <dt>Runtime</dt>
                                            <dd><?= Security::escape($runtime) ?></dd>
                                        </div>
                                    </dl>
                                <?php endif; ?>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>

            <nav class="pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= buildFilterQuery(array_merge($baseQuery, ['page' => $page - 1])) ?>"
                       class="btn btn--secondary">&larr; Previous</a>
                <?php endif; ?>
                <span class="pagination__current">Page <?= $page ?></span>
                <?php if (count($results) >= 20): ?>
                    <a href="?<?= buildFilterQuery(array_merge($baseQuery, ['page' => $page + 1])) ?>"
                       class="btn btn--secondary">Next &rarr;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

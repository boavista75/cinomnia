# Cinomnia

Private PHP web app for browsing movies and TV shows via [The Movie Database (TMDB)](https://www.themoviedb.org/). Custom lists, ratings, watch history, and personal notes. No database, no login, and no public registration.

The current UI is an Apple-inspired **liquid-glass** shell: space gray / black / white, iOS-blue accents, frosted navigation, and poster cards with compositor-only hover motion.

## Features

- Browse and search movies/TV with filters (genre, year, rating, sort)
- Live search suggestions while typing
- **Typo-tolerant search** — a misspelled title still ranks the real one first (`The Hounting of Hill House` → *The Haunting of Hill House*)
- Private local app — no login; lists, ratings, and notes are always available
- Custom lists plus system lists: **Want to Watch**, **Currently Watching** (TV only), and **Watched**
- Per-title score (1–10) and private notes
- Poster overlays: status chips (**Want** / **Currently** / **Watched**), star + score, Apple TV glyph when the title streams on Apple TV+
- Cast names on the details page open a Google search for that person
- Loading overlay for slow TMDB requests; light/dark theme
- Works on local Apache (XAMPP/LAMPP) and free shared hosting (HTTPS, optional SSL verify)

## What’s new — 16 September 2026

Work from that day’s sessions, in the order it landed.

### Private app (login removed)

The app is personal-use only. Login is gone.

- Deleted `login.php`, `logout.php`, and `src/Auth/AuthService.php`
- No session gate on Home, My Lists, details, or API actions
- Navbar no longer has a profile chip or Logout
- `.env` only needs `TMDB_API_KEY` — `APP_USERNAME` / `APP_PASSWORD` / `APP_PASSWORD_HASH` are unused
- Existing JSON data in `data/store.json` is unchanged (same owner lists, ratings, and notes)

### Library lists and poster tags

- Removed the auto-synced **You Have Rated** list. Rating a title no longer creates a list; the score still lives on the title and on the poster
- **Want to Watch** rows now have a **Watched** button next to **Remove** — one click moves the title to Watched and drops it from Want to Watch
- Every poster (home, lists, details, collections, search suggestions) can show:
  - **Want** / **Currently** / **Watched**
  - a star plus your score (e.g. ★ 8), not “You 8”
- **Currently Watching** is TV-only: a details-page toggle, an orange **Currently** chip on the poster, and a matching system list. Turning it on removes the show from Want to Watch / Watched; marking **Watched** clears Currently Watching
- On TV details, **Currently Watching** sits inside the Library panel with Want to Watch and Watched (no overflowing capsule)
- Library toasts no longer stretch the panel. Success text is a small overlay at the bottom of the dock, with a fade/slide, so the height stays stable

### Smart search

`src/Services/TitleMatcher.php` ranks TMDB hits by letter and pronunciation similarity.

- Exact TMDB search still runs first
- If there is no strong hit, fallback queries drop the likely-typo word (e.g. *Hill House*)
- Results are re-ranked so the intended title surfaces in both live suggestions and “See all results”
- Typical typos work the same way: *Incepton* → *Inception*, *Strenger Things* → *Stranger Things*

### Cast links and Apple TV

- Each actor/actress on the details page is a link to `https://www.google.com/search?q=…` (new tab)
- Titles available on **Apple TV+** (TMDB / JustWatch watch-provider data, any region) show a bitten-apple mark on the poster and an **Available on Apple TV** line on the details page

### Liquid-glass poster chips

- Movie/TV type, TMDB score, Apple glyph, and your score use a frosted glass chip (readable on light and dark posters)
- Library status stays color-coded on purpose:
  - **Want** — blue
  - **Currently** — amber
  - **Watched** — green
- The Want chip label is **Want** on posters so it fits; the details button is still **Want to Watch**

### Graphite Glass UI

Two redesign passes (clean Apple layout, then a fuller restructure) without dropping features.

- Palette: pure black, space gray, white, one iOS-blue accent; light theme kept
- Navigation, menus, and cards use `backdrop-filter` glass, 1px specular hairlines, and a soft ambient gradient behind the glass
- Pill-shaped controls, system-ui typography, more whitespace, card/grid layout
- Motion is `transform` / `opacity` only, hover-gated, and `prefers-reduced-motion` safe
- Backdrop-filter is limited to a few large surfaces so a 40-poster grid does not stack 40 blur layers
- Login-only CSS (`.auth-*`, profile avatar) was removed with the auth pages; JS hooks and IDs used by search, lists, ratings, and the library dock stayed in place

## What changed from the previous public version

The GitHub repo previously described a multi-user MySQL app (registration, comments, admin panel). That stack is gone. This is now a private single-owner library.

### Removed

- MySQL/MariaDB, PDO wrapper, and `database/setup.sql`
- Public registration (`register.php`)
- Public comments / discussion threads
- Admin panel and user-management services
- Single-owner login (`login.php`, `logout.php`, `AuthService`)

### Added

- JSON file store (`data/store.json`) with file locking (`src/Storage/JsonStore.php`)
- `NoteService` — private notes on each title instead of comments
- System lists: **Want to Watch**, **Currently Watching**, **Watched**
- Live search API (`search-api.php`) and typeahead (`js/search.js`)
- Fuzzy title matching (`src/Services/TitleMatcher.php`)
- Apple TV+ availability on posters and details
- Global loading overlay (`js/loading.js`)
- Hosting helpers: `TMDB_SSL_VERIFY`, `APP_DEBUG`, HTTPS redirect, HSTS

### Fixed / improved

- Pages flush early so the loading screen can appear before TMDB work finishes
- Browse grid collects TMDB’s 20-item pages into 24-title rows
- Details page: library dock, score picker, trailer, notes, cast Google links
- CSRF and session cookie path stay consistent for pages and API endpoints
- `.htaccess` blocks web access to `config/`, `src/`, `includes/`, and `data/`

## Requirements

- PHP 8.1+ (tested on 8.3)
- Apache with `mod_rewrite` (optional; project includes `.htaccess`)
- A free [TMDB API key](https://www.themoviedb.org/settings/api)

## Setup

1. **Clone the repository** into your web root (e.g. `htdocs/cinomnia`):

   ```bash
   git clone https://github.com/boavista75/cinomnia.git
   cd cinomnia
   ```

2. **Configure environment variables** — copy the example file and edit it:

   ```bash
   cp .env.example .env
   ```

   Set at least:

   | Variable       | Description       |
   |----------------|-------------------|
   | `TMDB_API_KEY` | Your TMDB API key |

   Never commit `.env` — it is listed in `.gitignore`.

3. **Make `data/` writable** by the web server (lists, ratings, and notes live in `data/store.json`).

4. **Open the app** in the browser, e.g. `http://localhost/cinomnia/`.

## Project structure

```
cinomnia/
├── config/config.php      # App config (loads secrets from .env)
├── data/                  # JSON store (not served over HTTP)
├── includes/              # Bootstrap, header, navbar, footer
├── src/
│   ├── Auth/              # Lists, ratings, notes
│   ├── Storage/           # JSON file store
│   ├── Security/          # Sessions, CSRF, XSS helpers
│   └── Services/          # TMDB client + TitleMatcher (fuzzy search)
├── css/  js/  assets/
├── .env.example           # Template for local secrets
└── *.php                  # Public pages and API endpoints
```

## Security notes

- Secrets live in `.env` only; do not hardcode API keys.
- The app is intended for private personal use and has no login.
- Forms and AJAX use CSRF tokens.
- If an API key was ever committed or shared, rotate it in the [TMDB settings](https://www.themoviedb.org/settings/api).

## License

This project is provided for educational / personal use.

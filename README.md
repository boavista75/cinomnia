# Cinomnia

Private PHP web app for browsing movies and TV shows via [The Movie Database (TMDB)](https://www.themoviedb.org/). Single-owner login, custom lists, ratings, watch history, and personal notes. No database and no public registration.

## Features

- Browse and search movies/TV with filters (genre, year, rating, sort)
- Live search suggestions while typing
- One owner account (credentials in `.env`); every other page requires a session
- Custom lists plus system lists: **Want to Watch**, **Watched**, and **You Have Rated**
- Per-title score (1–10) and private notes
- Loading overlay for slow TMDB requests; light/dark theme
- Works on local Apache (XAMPP/LAMPP) and free shared hosting (HTTPS, optional SSL verify)

## What changed from the previous public version

The GitHub repo previously described a multi-user MySQL app (registration, comments, admin panel). That stack is gone. This is now a private single-owner library.

### Removed

- MySQL/MariaDB, PDO wrapper, and `database/setup.sql`
- Public registration (`register.php`)
- Public comments / discussion threads
- Admin panel and user-management services

### Added

- JSON file store (`data/store.json`) with file locking (`src/Storage/JsonStore.php`)
- `NoteService` — private notes on each title instead of comments
- **Want to Watch** system list and toggle on the details page
- Live search API (`search-api.php`) and typeahead (`js/search.js`)
- Global loading overlay (`js/loading.js`)
- Hosting helpers: `TMDB_SSL_VERIFY`, `APP_DEBUG`, HTTPS redirect, HSTS

### Fixed / improved

- Login is env-based (`APP_USERNAME` + `APP_PASSWORD_HASH`); no user table
- Pages flush early so the loading screen can appear before TMDB work finishes
- Browse grid collects TMDB’s 20-item pages into 24-title rows
- Details page: library dock, score picker, trailer, notes
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

   | Variable            | Description                                      |
   |---------------------|--------------------------------------------------|
   | `APP_USERNAME`      | Your login username                              |
   | `APP_PASSWORD_HASH` | `password_hash()` of your password (preferred)   |
   | `APP_PASSWORD`      | Plaintext password, if you skip the hash         |
   | `TMDB_API_KEY`      | Your TMDB API key                                |

   Generate a hash:

   ```bash
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
   ```

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
│   ├── Auth/              # Login, lists, ratings, notes
│   ├── Storage/           # JSON file store
│   ├── Security/          # Sessions, CSRF, XSS helpers
│   └── Services/          # TMDB API client
├── css/  js/  assets/
├── .env.example           # Template for local secrets
└── *.php                  # Public pages and API endpoints
```

## Security notes

- Secrets live in `.env` only; do not hardcode API keys or passwords.
- The app is private: every page except login requires a session.
- Forms and AJAX use CSRF tokens.
- If an API key was ever committed or shared, rotate it in the [TMDB settings](https://www.themoviedb.org/settings/api).

## License

This project is provided for educational / personal use.

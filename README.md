# Cinomnia

PHP web app for browsing movies and TV shows via [The Movie Database (TMDB)](https://www.themoviedb.org/), with user accounts, custom lists, ratings, comments, and an admin panel.

## Features

- Browse and search movies/TV with filters (genre, year, rating, sort)
- User registration and login (session-based, CSRF-protected)
- Custom lists, ratings, and watch history
- Comments on titles
- Admin panel for user management

## Requirements

- PHP 8.1+ (tested on 8.3)
- MySQL / MariaDB (e.g. XAMPP / LAMPP)
- Apache with `mod_rewrite` (optional; project includes `.htaccess`)
- A free [TMDB API key](https://www.themoviedb.org/settings/api)

## Setup

1. **Clone the repository** into your web root (e.g. `htdocs/cinomnia`):

   ```bash
   git clone https://github.com/YOUR_USERNAME/cinomnia.git
   cd cinomnia
   ```

2. **Configure environment variables** — copy the example file and edit it:

   ```bash
   cp .env.example .env
   ```

   Set at least:

   | Variable       | Description                          |
   |----------------|--------------------------------------|
   | `DB_HOST`      | MySQL host (default `localhost`)     |
   | `DB_NAME`      | Database name (default `cinomnia`)   |
   | `DB_USER`      | MySQL user                           |
   | `DB_PASS`      | MySQL password                       |
   | `TMDB_API_KEY` | Your TMDB API key                    |

   Never commit `.env` — it is listed in `.gitignore`.

3. **Create the database** in phpMyAdmin (or CLI), then run the schema:

   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS cinomnia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p cinomnia < database/setup.sql
   ```

4. **Open the app** in the browser, e.g. `http://localhost/cinomnia/`.

## Project structure

```
cinomnia/
├── config/config.php      # App config (loads secrets from .env)
├── database/setup.sql     # Schema
├── includes/              # Bootstrap, header, navbar, footer
├── src/
│   ├── Auth/              # Auth, lists, comments, admin services
│   ├── Database/          # PDO wrapper
│   ├── Security/          # Sessions, CSRF, XSS helpers
│   └── Services/          # TMDB API client
├── css/  js/  assets/
├── .env.example           # Template for local secrets
└── *.php                  # Public pages and API endpoints
```

## Security notes

- Secrets live in `.env` only; do not hardcode API keys or DB passwords.
- Passwords are stored with `password_hash()` / `password_verify()`.
- Forms and AJAX use CSRF tokens.
- If an API key was ever committed or shared, rotate it in the [TMDB settings](https://www.themoviedb.org/settings/api).

## License

This project is provided for educational / personal use.

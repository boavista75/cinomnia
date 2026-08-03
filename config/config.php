<?php

declare(strict_types=1);

/**
 * Cinomnia - Global Application Configuration
 *
 * Central configuration file for database credentials, TMDB API settings,
 * and session security parameters. Loaded once via bootstrap.php.
 *
 * Secrets (DB_*, TMDB_API_KEY) are loaded from a .env file in the project root.
 * Copy .env.example to .env and fill in your values — never commit .env.
 */

// Prevent direct access to this file via the web server
if (!defined('CINOMNIA_APP')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

/**
 * Load KEY=VALUE pairs from a .env file into the environment.
 * Existing environment variables are not overwritten.
 */
function cinomniaLoadEnv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        // Strip matching single or double quotes
        if (
            strlen($value) >= 2
            && (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

/**
 * Read an environment variable with an optional default.
 */
function cinomniaEnv(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

cinomniaLoadEnv(dirname(__DIR__) . '/.env');

// ---------------------------------------------------------------------------
// Database (MySQL via LAMPP default credentials)
// ---------------------------------------------------------------------------
define('DB_HOST',    cinomniaEnv('DB_HOST', 'localhost'));
define('DB_NAME',    cinomniaEnv('DB_NAME', 'cinomnia'));
define('DB_USER',    cinomniaEnv('DB_USER', 'root'));
define('DB_PASS',    cinomniaEnv('DB_PASS', ''));
define('DB_CHARSET', cinomniaEnv('DB_CHARSET', 'utf8mb4'));

// ---------------------------------------------------------------------------
// TMDB API (The Movie Database)
// https://developer.themoviedb.org/docs
// ---------------------------------------------------------------------------
define('TMDB_API_KEY', cinomniaEnv('TMDB_API_KEY'));
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');
define('TMDB_IMG_BASE', 'https://image.tmdb.org/t/p');

if (TMDB_API_KEY === '') {
    throw new RuntimeException(
        'TMDB_API_KEY is not set. Copy .env.example to .env and add your API key.'
    );
}

// Poster sizes: w185 (grid), w500 (details), original (backdrop)
const TMDB_POSTER_SIZE   = 'w500';
const TMDB_POSTER_GRID   = 'w342';
const TMDB_BACKDROP_SIZE = 'w1280';

// ---------------------------------------------------------------------------
// Application paths & URLs
// ---------------------------------------------------------------------------
const APP_NAME = 'Cinomnia';
const APP_ROOT = __DIR__ . '/..';

/**
 * Application web root (always the project folder, not the current script subfolder).
 * Ensures session cookies and asset URLs stay consistent for pages and /api/* endpoints.
 */
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseDir    = rtrim(dirname($scriptName), '/');

if (str_ends_with($baseDir, '/api')) {
    $baseDir = substr($baseDir, 0, -4);
}

define('BASE_URL', $baseDir ?: '');

/** Session cookie path — always the app root so pages and API share one session. */
define('SESSION_COOKIE_PATH', BASE_URL !== '' ? BASE_URL : '/');

// ---------------------------------------------------------------------------
// Session security settings
// ---------------------------------------------------------------------------
const SESSION_NAME         = 'CINOMNIA_SESSION';
const SESSION_LIFETIME     = 3600;       // 1 hour in seconds
const SESSION_REGEN_INTERVAL = 300;      // Regenerate session ID every 5 minutes

// ---------------------------------------------------------------------------
// Password hashing (PHP will use bcrypt by default; Argon2id if available)
// ---------------------------------------------------------------------------
const PASSWORD_ALGO = PASSWORD_DEFAULT;

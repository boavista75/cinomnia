<?php

declare(strict_types=1);

namespace Cinomnia\Security;

/**
 * Security - CSRF, XSS, and Session Hardening Utilities
 *
 * Centralises security helpers used across forms and output rendering.
 * All user-supplied data displayed in HTML must pass through escape().
 */
final class Security
{
    /**
     * Initialise a secure PHP session with hijacking-prevention flags.
     * Called once from bootstrap.php before any output.
     */
    public static function initSession(): void
    {
        self::expireLegacySessionCookies();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => SESSION_COOKIE_PATH,
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_name(SESSION_NAME);
            session_start();
        }

        self::refreshSessionCookie();

        // Bind session to client fingerprint to detect hijacking attempts
        $fingerprint = self::buildFingerprint();

        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
            // Possible session hijacking — destroy and start fresh
            self::destroySession();
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => SESSION_COOKIE_PATH,
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name(SESSION_NAME);
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
            self::refreshSessionCookie();
        }

        // Periodically regenerate session ID to limit fixation window
        $now = time();
        if (
            !isset($_SESSION['_last_regen'])
            || ($now - (int) $_SESSION['_last_regen']) > SESSION_REGEN_INTERVAL
        ) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = $now;
            self::refreshSessionCookie();
        }
    }

    /**
     * Re-issue the session cookie with the canonical app-root path.
     * Prevents stale cookies scoped to /api from shadowing the real session.
     */
    public static function refreshSessionCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();

        setcookie(session_name(), session_id(), [
            'expires'  => $params['lifetime'] > 0 ? time() + (int) $params['lifetime'] : 0,
            'path'     => SESSION_COOKIE_PATH,
            'domain'   => $params['domain'],
            'secure'   => (bool) $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Remove session cookies that were incorrectly scoped to the /api subfolder.
     */
    private static function expireLegacySessionCookies(): void
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }

        $legacyPaths = array_unique(array_filter([
            SESSION_COOKIE_PATH . '/api',
            '/api',
        ]));

        foreach ($legacyPaths as $path) {
            setcookie(SESSION_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => $path,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Build a hash from stable client attributes (not foolproof, but adds a layer).
     */
    private static function buildFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $userAgent);
    }

    /**
     * Destroy the current session completely.
     */
    public static function destroySession(): void
    {
        $sessionName = session_name();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        if (!ini_get('session.use_cookies')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return;
        }

        $paths = array_unique(array_filter([
            SESSION_COOKIE_PATH,
            SESSION_COOKIE_PATH . '/api',
            '/api',
        ]));

        foreach ($paths as $path) {
            setcookie($sessionName, '', [
                'expires'  => time() - 42000,
                'path'     => $path,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Generate or retrieve the CSRF token stored in the session.
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Read a CSRF token from POST data, a request header, or both.
     */
    public static function getRequestCsrfToken(): string
    {
        $token = trim((string) ($_POST['csrf_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    }

    /**
     * Validate a submitted CSRF token using timing-safe comparison.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        if (empty($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Escape output for safe HTML rendering (XSS prevention).
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Render a hidden CSRF input field for forms.
     */
    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::escape($token) . '">';
    }
}

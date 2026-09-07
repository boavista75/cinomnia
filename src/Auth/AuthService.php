<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Security\Security;

/**
 * Single-owner login against credentials in .env (no registration, no user table).
 */
final class AuthService
{
    private const OWNER_USER_ID = 1;

    /**
     * Authenticate the single owner and establish a secure session.
     *
     * @return array{success: bool, message: string}
     */
    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Please enter your credentials.'];
        }

        $username = defined('APP_USERNAME') ? (string) APP_USERNAME : '';
        $hash     = defined('APP_PASSWORD_HASH') ? (string) APP_PASSWORD_HASH : '';
        $plain    = defined('APP_PASSWORD') ? (string) APP_PASSWORD : '';

        if ($username === '' || ($hash === '' && $plain === '')) {
            return ['success' => false, 'message' => 'Login is not configured. Set APP_USERNAME and a password in .env.'];
        }

        $userOk = hash_equals($username, $identifier);
        $passOk = false;

        if ($hash !== '') {
            $passOk = password_verify($password, $hash);
        } elseif ($plain !== '') {
            $passOk = hash_equals($plain, $password);
        }

        if (!$userOk || !$passOk) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        session_regenerate_id(true);

        $_SESSION['user_id']      = self::OWNER_USER_ID;
        $_SESSION['username']     = $username;
        $_SESSION['_last_regen']  = time();
        $_SESSION['_fingerprint'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        Security::refreshSessionCookie();

        return ['success' => true, 'message' => 'Welcome back, ' . $username . '!'];
    }

    public function logout(): void
    {
        Security::destroySession();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['username']);
    }

    public function getUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

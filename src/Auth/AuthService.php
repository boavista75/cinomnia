<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Database\Database;
use Cinomnia\Security\Security;
use PDO;
use RuntimeException;

/**
 * AuthService - User Registration, Login, and Session Management
 *
 * Handles all authentication logic using PDO prepared statements
 * and PHP's password_hash / password_verify for secure credential storage.
 */
final class AuthService
{
    /**
     * Register a new user after validating input.
     *
     * @return array{success: bool, message: string}
     */
    public function register(string $username, string $email, string $password, string $confirmPassword): array
    {
        $username = trim($username);
        $email    = trim($email);

        // --- Server-side validation ---
        if ($username === '' || $email === '' || $password === '') {
            return ['success' => false, 'message' => 'All fields are required.'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return [
                'success' => false,
                'message' => 'Username must be 3–50 characters (letters, numbers, underscore only).',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        // --- Hash password with bcrypt/argon2 (PASSWORD_DEFAULT) ---
        $passwordHash = password_hash($password, PASSWORD_ALGO);

        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $pdo = Database::getConnection();

        // Check for duplicate username or email before insert
        $check = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $check->execute(['username' => $username, 'email' => $email]);

        if ($check->fetch()) {
            return ['success' => false, 'message' => 'Username or email is already registered.'];
        }

        // Insert new user with prepared statement
        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, created_at)
             VALUES (:username, :email, :password_hash, NOW())'
        );

        $insert->execute([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
        ]);

        return ['success' => true, 'message' => 'Registration successful! You can now log in.'];
    }

    /**
     * Authenticate user credentials and establish a secure session.
     *
     * @return array{success: bool, message: string}
     */
    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Please enter your credentials.'];
        }

        $pdo = Database::getConnection();

        // Allow login with username OR email (distinct placeholders — native PDO rejects reused names)
        $stmt = $pdo->prepare(
            'SELECT id, username, email, password_hash, is_admin
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $stmt->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Generic error message prevents user enumeration attacks
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Re-hash if PHP's default algorithm has been upgraded
        if (password_needs_rehash($user['password_hash'], PASSWORD_ALGO)) {
            $newHash = password_hash($password, PASSWORD_ALGO);
            $update  = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute(['hash' => $newHash, 'id' => $user['id']]);
        }

        // Regenerate session ID on login to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['is_admin']      = (int) ($user['is_admin'] ?? 0) === 1;
        $_SESSION['_last_regen']   = time();
        $_SESSION['_fingerprint']  = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        Security::refreshSessionCookie();

        return ['success' => true, 'message' => 'Welcome back, ' . $user['username'] . '!'];
    }

    /**
     * End the user session securely.
     */
    public function logout(): void
    {
        Security::destroySession();
    }

    /**
     * Check whether a user is currently authenticated.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['username']);
    }

    /**
     * Get the logged-in username, or null if guest.
     */
    public function getUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get the logged-in user ID, or null if guest.
     */
    public function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Quick session hint for admin UI (always re-verify in AdminService for mutations).
     */
    public function isAdminSession(): bool
    {
        return !empty($_SESSION['is_admin']);
    }
}

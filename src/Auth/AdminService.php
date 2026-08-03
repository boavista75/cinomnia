<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Database\Database;
use PDO;
use RuntimeException;

/**
 * AdminService — privileged user management, comment moderation, and ratings overview.
 *
 * All mutating operations require the acting user to be verified as admin in the database.
 */
final class AdminService
{
    /**
     * Verify admin status from the database (never trust session alone).
     */
    public function isAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && (int) $row['is_admin'] === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllUsers(): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT id, username, email, is_admin, created_at
             FROM users
             ORDER BY created_at DESC'
        );

        $users = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = [
                'id'         => (int) $row['id'],
                'username'   => $row['username'],
                'email'      => $row['email'],
                'is_admin'   => (int) $row['is_admin'] === 1,
                'created_at' => $row['created_at'],
            ];
        }

        return $users;
    }

    /**
     * @return array{success: bool, message: string, user_id?: int}
     */
    public function createUser(
        int $actingAdminId,
        string $username,
        string $email,
        string $password,
        bool $isAdmin = false
    ): array {
        if (!$this->isAdmin($actingAdminId)) {
            return ['success' => false, 'message' => 'Access denied.'];
        }

        $username = trim($username);
        $email    = trim($email);

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

        $pdo = Database::getConnection();

        $check = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $check->execute(['username' => $username, 'email' => $email]);

        if ($check->fetch()) {
            return ['success' => false, 'message' => 'Username or email is already registered.'];
        }

        $passwordHash = password_hash($password, PASSWORD_ALGO);

        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, is_admin, created_at)
             VALUES (:username, :email, :password_hash, :is_admin, NOW())'
        );
        $insert->execute([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'is_admin'      => $isAdmin ? 1 : 0,
        ]);

        return [
            'success' => true,
            'message' => 'User created successfully.',
            'user_id' => (int) $pdo->lastInsertId(),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateUsername(int $actingAdminId, int $userId, string $username): array
    {
        if (!$this->isAdmin($actingAdminId)) {
            return ['success' => false, 'message' => 'Access denied.'];
        }

        $username = trim($username);

        if ($userId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return [
                'success' => false,
                'message' => 'Username must be 3–50 characters (letters, numbers, underscore only).',
            ];
        }

        $pdo = Database::getConnection();

        $check = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1'
        );
        $check->execute(['username' => $username, 'id' => $userId]);

        if ($check->fetch()) {
            return ['success' => false, 'message' => 'That username is already taken.'];
        }

        $update = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');
        $update->execute(['username' => $username, 'id' => $userId]);

        if ($update->rowCount() === 0) {
            return ['success' => false, 'message' => 'User not found or username unchanged.'];
        }

        return ['success' => true, 'message' => 'Username updated.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteUser(int $actingAdminId, int $userId): array
    {
        if (!$this->isAdmin($actingAdminId)) {
            return ['success' => false, 'message' => 'Access denied.'];
        }

        if ($userId <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }

        if ($userId === $actingAdminId) {
            return ['success' => false, 'message' => 'You cannot delete your own account.'];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        return ['success' => true, 'message' => 'User deleted.'];
    }

    /**
     * Media items that have at least one comment (top-level or reply).
     *
     * @return list<array<string, mixed>>
     */
    public function getMediaWithComments(): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT c.tmdb_id, c.media_type,
                    COUNT(c.id) AS comment_count,
                    MAX(c.created_at) AS last_comment_at,
                    (SELECT urh.title FROM user_ratings_history urh
                     WHERE urh.tmdb_id = c.tmdb_id AND urh.media_type = c.media_type
                       AND urh.title IS NOT NULL
                     LIMIT 1) AS title
             FROM comments c
             GROUP BY c.tmdb_id, c.media_type
             ORDER BY last_comment_at DESC'
        );

        $items = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'tmdb_id'          => (int) $row['tmdb_id'],
                'media_type'       => $row['media_type'],
                'comment_count'    => (int) $row['comment_count'],
                'last_comment_at'  => $row['last_comment_at'],
                'title'            => $row['title'],
            ];
        }

        return $items;
    }

    /**
     * All comments for a specific TMDB item (flat list with reply indicator).
     *
     * @param 'movie'|'tv' $mediaType
     * @return list<array<string, mixed>>
     */
    public function getCommentsForMedia(int $tmdbId, string $mediaType): array
    {
        if (!in_array($mediaType, ['movie', 'tv'], true) || $tmdbId <= 0) {
            return [];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.user_id, c.body, c.parent_comment_id, c.created_at, u.username,
                    pc.body AS parent_body,
                    pu.username AS parent_username
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN comments pc ON pc.id = c.parent_comment_id
             LEFT JOIN users pu ON pu.id = pc.user_id
             WHERE c.tmdb_id = :tmdb_id AND c.media_type = :media_type
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['tmdb_id' => $tmdbId, 'media_type' => $mediaType]);

        $comments = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comments[] = [
                'id'                => (int) $row['id'],
                'user_id'           => (int) $row['user_id'],
                'username'          => $row['username'],
                'body'              => $row['body'],
                'parent_comment_id' => $row['parent_comment_id'] !== null
                    ? (int) $row['parent_comment_id']
                    : null,
                'parent_username'   => $row['parent_username'],
                'parent_body'       => $row['parent_body'],
                'created_at'        => $row['created_at'],
                'is_reply'          => $row['parent_comment_id'] !== null,
            ];
        }

        return $comments;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteComment(int $actingAdminId, int $commentId): array
    {
        if (!$this->isAdmin($actingAdminId)) {
            return ['success' => false, 'message' => 'Access denied.'];
        }

        if ($commentId <= 0) {
            return ['success' => false, 'message' => 'Invalid comment.'];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute(['id' => $commentId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Comment not found.'];
        }

        return ['success' => true, 'message' => 'Comment deleted.'];
    }

    /**
     * Aggregated local ratings per TMDB item with individual user ratings.
     *
     * @return list<array<string, mixed>>
     */
    public function getLocalRatingsOverview(): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT lr.tmdb_id, lr.media_type,
                    COUNT(lr.id) AS rating_count,
                    ROUND(AVG(lr.rating_value), 1) AS avg_rating,
                    MAX(lr.updated_at) AS last_rated_at,
                    (SELECT urh.title FROM user_ratings_history urh
                     WHERE urh.tmdb_id = lr.tmdb_id AND urh.media_type = lr.media_type
                       AND urh.title IS NOT NULL
                     LIMIT 1) AS title,
                    (SELECT urh.poster_path FROM user_ratings_history urh
                     WHERE urh.tmdb_id = lr.tmdb_id AND urh.media_type = lr.media_type
                       AND urh.poster_path IS NOT NULL
                     LIMIT 1) AS poster_path
             FROM local_ratings lr
             GROUP BY lr.tmdb_id, lr.media_type
             ORDER BY last_rated_at DESC'
        );

        $overview = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tmdbId    = (int) $row['tmdb_id'];
            $mediaType = $row['media_type'];

            $overview[] = [
                'tmdb_id'       => $tmdbId,
                'media_type'    => $mediaType,
                'rating_count'  => (int) $row['rating_count'],
                'avg_rating'    => (float) $row['avg_rating'],
                'last_rated_at' => $row['last_rated_at'],
                'title'         => $row['title'],
                'poster_path'   => $row['poster_path'],
                'ratings'       => $this->getIndividualRatings($tmdbId, $mediaType),
            ];
        }

        return $overview;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return list<array<string, mixed>>
     */
    private function getIndividualRatings(int $tmdbId, string $mediaType): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT lr.id, lr.user_id, lr.rating_value, lr.created_at, lr.updated_at, u.username
             FROM local_ratings lr
             INNER JOIN users u ON u.id = lr.user_id
             WHERE lr.tmdb_id = :tmdb_id AND lr.media_type = :media_type
             ORDER BY lr.updated_at DESC'
        );
        $stmt->execute(['tmdb_id' => $tmdbId, 'media_type' => $mediaType]);

        $ratings = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ratings[] = [
                'id'           => (int) $row['id'],
                'user_id'      => (int) $row['user_id'],
                'username'     => $row['username'],
                'rating_value' => (int) $row['rating_value'],
                'created_at'   => $row['created_at'],
                'updated_at'   => $row['updated_at'],
            ];
        }

        return $ratings;
    }

    /**
     * Dashboard summary counts for the admin header.
     *
     * @return array{users: int, commented_media: int, rated_media: int, total_comments: int}
     */
    public function getDashboardStats(): array
    {
        $pdo = Database::getConnection();

        return [
            'users'            => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'commented_media'  => (int) $pdo->query(
                'SELECT COUNT(*) FROM (SELECT DISTINCT tmdb_id, media_type FROM comments) AS cm'
            )->fetchColumn(),
            'rated_media'      => (int) $pdo->query(
                'SELECT COUNT(*) FROM (SELECT DISTINCT tmdb_id, media_type FROM local_ratings) AS lr'
            )->fetchColumn(),
            'total_comments'   => (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Database\Database;
use PDO;

/**
 * CommentService — threaded comments and like/dislike reactions per TMDB item.
 */
final class CommentService
{
    private const MAX_BODY_LENGTH = 2000;

    /**
     * @param 'movie'|'tv' $mediaType
     * @return list<array<string, mixed>>
     */
    public function getThreadedComments(int $tmdbId, string $mediaType, ?int $viewerUserId = null): array
    {
        if (!$this->isValidMediaType($mediaType) || $tmdbId <= 0) {
            return [];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.user_id, c.body, c.parent_comment_id, c.created_at, u.username,
                    (SELECT COUNT(*) FROM comment_reactions cr
                     WHERE cr.comment_id = c.id AND cr.reaction_type = 1) AS likes,
                    (SELECT COUNT(*) FROM comment_reactions cr
                     WHERE cr.comment_id = c.id AND cr.reaction_type = -1) AS dislikes
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.tmdb_id = :tmdb_id AND c.media_type = :media_type
             ORDER BY c.created_at ASC'
        );
        $stmt->execute(['tmdb_id' => $tmdbId, 'media_type' => $mediaType]);

        $rows     = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $viewerRx = $viewerUserId !== null
            ? $this->getUserReactionsForComments($viewerUserId, array_column($rows, 'id'))
            : [];

        $topLevel = [];
        $replies  = [];

        foreach ($rows as $row) {
            $comment = $this->formatCommentRow($row, $viewerRx);

            if ($row['parent_comment_id'] === null) {
                $comment['replies'] = [];
                $topLevel[(int) $row['id']] = $comment;
            } else {
                $parentId = (int) $row['parent_comment_id'];
                $replies[$parentId][] = $comment;
            }
        }

        foreach ($replies as $parentId => $children) {
            if (isset($topLevel[$parentId])) {
                $topLevel[$parentId]['replies'] = $children;
            }
        }

        $result = array_values($topLevel);
        usort($result, static fn(array $a, array $b): int =>
            strcmp($b['created_at'], $a['created_at']));

        return $result;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, comment?: array<string, mixed>}
     */
    public function postComment(
        int $userId,
        int $tmdbId,
        string $mediaType,
        string $body,
        ?int $parentCommentId = null
    ): array {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        $body = trim($body);

        if ($body === '') {
            return ['success' => false, 'message' => 'Comment cannot be empty.'];
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            return [
                'success' => false,
                'message' => 'Comment must be ' . self::MAX_BODY_LENGTH . ' characters or fewer.',
            ];
        }

        if ($parentCommentId !== null) {
            $parentCheck = $this->validateReplyParent($parentCommentId, $tmdbId, $mediaType);

            if ($parentCheck !== true) {
                return ['success' => false, 'message' => $parentCheck];
            }
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO comments (user_id, tmdb_id, media_type, parent_comment_id, body, created_at, updated_at)
             VALUES (:user_id, :tmdb_id, :media_type, :parent_id, :body, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'tmdb_id'    => $tmdbId,
            'media_type' => $mediaType,
            'parent_id'  => $parentCommentId,
            'body'       => $body,
        ]);

        $commentId = (int) $pdo->lastInsertId();
        $comment   = $this->getCommentById($commentId, $userId);

        return [
            'success' => true,
            'message' => $parentCommentId !== null ? 'Reply posted.' : 'Comment posted.',
            'comment' => $comment,
        ];
    }

    /**
     * @param int $reactionType 1 = like, -1 = dislike, 0 = remove vote
     * @return array{success: bool, message: string, likes?: int, dislikes?: int, user_reaction?: int|null}
     */
    public function setReaction(int $userId, int $commentId, int $reactionType): array
    {
        if (!in_array($reactionType, [-1, 0, 1], true)) {
            return ['success' => false, 'message' => 'Invalid reaction type.'];
        }

        if (!$this->commentExists($commentId)) {
            return ['success' => false, 'message' => 'Comment not found.'];
        }

        $pdo      = Database::getConnection();
        $existing = $this->getUserReaction($userId, $commentId);

        if ($reactionType === 0 || $existing === $reactionType) {
            $delete = $pdo->prepare(
                'DELETE FROM comment_reactions
                 WHERE user_id = :user_id AND comment_id = :comment_id'
            );
            $delete->execute(['user_id' => $userId, 'comment_id' => $commentId]);
            $newUserReaction = null;
        } else {
            $upsert = $pdo->prepare(
                'INSERT INTO comment_reactions (user_id, comment_id, reaction_type, created_at, updated_at)
                 VALUES (:user_id, :comment_id, :reaction_type, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    reaction_type = VALUES(reaction_type),
                    updated_at    = NOW()'
            );
            $upsert->execute([
                'user_id'       => $userId,
                'comment_id'    => $commentId,
                'reaction_type' => $reactionType,
            ]);
            $newUserReaction = $reactionType;
        }

        $counts = $this->getReactionCounts($commentId);

        return [
            'success'       => true,
            'message'       => 'Reaction updated.',
            'likes'         => $counts['likes'],
            'dislikes'      => $counts['dislikes'],
            'user_reaction' => $newUserReaction,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCommentById(int $commentId, ?int $viewerUserId = null): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.user_id, c.body, c.parent_comment_id, c.created_at, u.username,
                    (SELECT COUNT(*) FROM comment_reactions cr
                     WHERE cr.comment_id = c.id AND cr.reaction_type = 1) AS likes,
                    (SELECT COUNT(*) FROM comment_reactions cr
                     WHERE cr.comment_id = c.id AND cr.reaction_type = -1) AS dislikes
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $viewerRx = $viewerUserId !== null
            ? $this->getUserReactionsForComments($viewerUserId, [$commentId])
            : [];

        $comment = $this->formatCommentRow($row, $viewerRx);
        $comment['replies'] = [];

        return $comment;
    }

    /**
     * @param list<int|string> $commentIds
     * @return array<int, int>
     */
    private function getUserReactionsForComments(int $userId, array $commentIds): array
    {
        $commentIds = array_values(array_filter(array_map('intval', $commentIds)));

        if ($commentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $pdo          = Database::getConnection();
        $stmt         = $pdo->prepare(
            "SELECT comment_id, reaction_type
             FROM comment_reactions
             WHERE user_id = ? AND comment_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$userId], $commentIds));

        $map = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) $row['comment_id']] = (int) $row['reaction_type'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, int> $viewerReactions
     * @return array<string, mixed>
     */
    private function formatCommentRow(array $row, array $viewerReactions): array
    {
        $id = (int) $row['id'];

        return [
            'id'                => $id,
            'user_id'           => (int) $row['user_id'],
            'username'          => $row['username'],
            'body'              => $row['body'],
            'parent_comment_id' => $row['parent_comment_id'] !== null
                ? (int) $row['parent_comment_id']
                : null,
            'created_at'        => $row['created_at'],
            'likes'             => (int) $row['likes'],
            'dislikes'          => (int) $row['dislikes'],
            'user_reaction'     => $viewerReactions[$id] ?? null,
        ];
    }

    /**
     * @return array{likes: int, dislikes: int}
     */
    private function getReactionCounts(int $commentId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT
                SUM(CASE WHEN reaction_type = 1 THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN reaction_type = -1 THEN 1 ELSE 0 END) AS dislikes
             FROM comment_reactions
             WHERE comment_id = :comment_id'
        );
        $stmt->execute(['comment_id' => $commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'likes'    => (int) ($row['likes'] ?? 0),
            'dislikes' => (int) ($row['dislikes'] ?? 0),
        ];
    }

    private function getUserReaction(int $userId, int $commentId): ?int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT reaction_type FROM comment_reactions
             WHERE user_id = :user_id AND comment_id = :comment_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'comment_id' => $commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['reaction_type'] : null;
    }

    private function commentExists(int $commentId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $commentId]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return true|string Error message when invalid.
     */
    private function validateReplyParent(int $parentCommentId, int $tmdbId, string $mediaType): bool|string
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, tmdb_id, media_type, parent_comment_id
             FROM comments
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $parentCommentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            return 'The comment you are replying to was not found.';
        }

        if ((int) $parent['tmdb_id'] !== $tmdbId || $parent['media_type'] !== $mediaType) {
            return 'Reply target does not match this title.';
        }

        if ($parent['parent_comment_id'] !== null) {
            return 'Replies can only be posted to top-level comments.';
        }

        return true;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function isValidMediaType(string $mediaType): bool
    {
        return in_array($mediaType, ['movie', 'tv'], true);
    }
}

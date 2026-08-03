-- ============================================================================
-- Cinomnia Database Setup Script
-- Database: cinomnia (must already exist in phpMyAdmin)
-- Run via phpMyAdmin SQL tab or: mysql -u root cinomnia < database/setup.sql
-- ============================================================================

USE `cinomnia`;

-- Drop legacy single watchlist table if present (replaced by custom_lists)
DROP TABLE IF EXISTS `watchlist`;

-- ----------------------------------------------------------------------------
-- users — authentication credentials
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)     NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `is_admin`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    KEY `idx_users_created_at`     (`created_at`),
    KEY `idx_users_is_admin`       (`is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- custom_lists — user-defined named collections
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `custom_lists` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_custom_lists_user_name` (`user_id`, `name`),
    KEY `idx_custom_lists_user_id` (`user_id`),
    CONSTRAINT `fk_custom_lists_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- list_items — TMDB titles within a custom list
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `list_items` (
    `id`          INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `list_id`     INT UNSIGNED        NOT NULL,
    `tmdb_id`     INT UNSIGNED        NOT NULL,
    `media_type`  ENUM('movie', 'tv') NOT NULL,
    `title`       VARCHAR(255)        DEFAULT NULL,
    `poster_path` VARCHAR(255)        DEFAULT NULL,
    `added_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_list_items_list_media` (`list_id`, `tmdb_id`, `media_type`),
    KEY `idx_list_items_list_id` (`list_id`),
    KEY `idx_list_items_tmdb`    (`tmdb_id`, `media_type`),
    CONSTRAINT `fk_list_items_list`
        FOREIGN KEY (`list_id`) REFERENCES `custom_lists` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user_ratings_history — per-user ratings (1–10) and watched status
-- One row per user + TMDB item; timestamps track when values changed.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_ratings_history` (
    `id`          INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED        NOT NULL,
    `tmdb_id`     INT UNSIGNED        NOT NULL,
    `media_type`  ENUM('movie', 'tv') NOT NULL,
    `rating`      TINYINT UNSIGNED    DEFAULT NULL COMMENT '1–10, NULL when unrated',
    `is_watched`  TINYINT(1)          NOT NULL DEFAULT 0,
    `title`       VARCHAR(255)        DEFAULT NULL,
    `poster_path` VARCHAR(255)        DEFAULT NULL,
    `rated_at`    DATETIME            DEFAULT NULL,
    `watched_at`  DATETIME            DEFAULT NULL,
    `created_at`  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_ratings_history_user_media` (`user_id`, `tmdb_id`, `media_type`),
    KEY `idx_user_ratings_history_user_id` (`user_id`),
    KEY `idx_user_ratings_history_watched` (`user_id`, `is_watched`),
    KEY `idx_user_ratings_history_rated`   (`user_id`, `rating`),
    CONSTRAINT `fk_user_ratings_history_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `chk_user_ratings_history_rating`
        CHECK (`rating` IS NULL OR (`rating` >= 1 AND `rating` <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- comments — user comments on TMDB titles (with optional one-level replies)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED        NOT NULL,
    `tmdb_id`           INT UNSIGNED        NOT NULL,
    `media_type`        ENUM('movie', 'tv') NOT NULL,
    `parent_comment_id` INT UNSIGNED        DEFAULT NULL,
    `body`              TEXT                NOT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_media` (`tmdb_id`, `media_type`),
    KEY `idx_comments_user_id` (`user_id`),
    KEY `idx_comments_parent` (`parent_comment_id`),
    CONSTRAINT `fk_comments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_comments_parent`
        FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- comment_reactions — per-user like (+1) or dislike (-1) on a comment
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comment_reactions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `comment_id`    INT UNSIGNED NOT NULL,
    `reaction_type` TINYINT      NOT NULL COMMENT '+1 like, -1 dislike',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_comment_reactions_user_comment` (`user_id`, `comment_id`),
    KEY `idx_comment_reactions_comment_id` (`comment_id`),
    CONSTRAINT `fk_comment_reactions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_comment_reactions_comment`
        FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `chk_comment_reactions_type`
        CHECK (`reaction_type` IN (1, -1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- local_ratings — per-user ratings separate from TMDB API scores
-- One row per user + TMDB item; used for community average calculations.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `local_ratings` (
    `id`           INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED        NOT NULL,
    `tmdb_id`      INT UNSIGNED        NOT NULL,
    `media_type`   ENUM('movie', 'tv') NOT NULL,
    `rating_value` TINYINT UNSIGNED    NOT NULL,
    `created_at`   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_local_ratings_user_media` (`user_id`, `tmdb_id`, `media_type`),
    KEY `idx_local_ratings_tmdb`    (`tmdb_id`, `media_type`),
    KEY `idx_local_ratings_user_id` (`user_id`),
    CONSTRAINT `fk_local_ratings_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `chk_local_ratings_value`
        CHECK (`rating_value` >= 1 AND `rating_value` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration helpers (run on existing databases that pre-date admin panel)
-- ============================================================================

-- Add admin role column to users (ignore error if column already exists)
-- ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`;

-- Promote a user to admin (replace username):
-- UPDATE `users` SET `is_admin` = 1 WHERE `username` = 'your_admin_username' LIMIT 1;

-- Backfill local_ratings from existing user_ratings_history rows:
-- INSERT INTO `local_ratings` (`user_id`, `tmdb_id`, `media_type`, `rating_value`, `created_at`, `updated_at`)
-- SELECT `user_id`, `tmdb_id`, `media_type`, `rating`, COALESCE(`rated_at`, `created_at`), COALESCE(`rated_at`, `updated_at`)
-- FROM `user_ratings_history`
-- WHERE `rating` IS NOT NULL
-- ON DUPLICATE KEY UPDATE
--     `rating_value` = VALUES(`rating_value`),
--     `updated_at`   = VALUES(`updated_at`);

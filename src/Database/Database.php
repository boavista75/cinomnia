<?php

declare(strict_types=1);

namespace Cinomnia\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database - PDO Singleton Wrapper
 *
 * Provides a single shared PDO connection with strict error handling
 * and prepared-statement-friendly defaults for the entire application.
 */
final class Database
{
    private static ?PDO $instance = null;

    /** Prevent direct instantiation */
    private function __construct() {}

    /**
     * Returns the shared PDO connection, creating it on first call.
     *
     * @throws RuntimeException When the connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // Native prepared statements
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Database connection failed. Ensure MySQL is running and the cinomnia database exists.',
                    0,
                    $e
                );
            }
        }

        return self::$instance;
    }

    /** Prevent cloning of the singleton */
    private function __clone() {}
}

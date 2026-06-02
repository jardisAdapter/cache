<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration;

use PDO;

/**
 * Helper class for database setup in tests.
 */
class DatabaseHelper
{
    /**
     * Create an in-memory SQLite database with cache table.
     *
     * @return PDO
     */
    public static function createSqliteDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create cache table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS cache (
                cache_key TEXT PRIMARY KEY,
                cache_value TEXT NOT NULL,
                expires_at INTEGER
            )
        ');

        // Create index for efficient expiration cleanup
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cache_expires_at ON cache(expires_at)');

        return $pdo;
    }
}

<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use PDO;
use PDOException;

/**
 * Database-based cache implementation using PDO.
 */
class CacheDatabase extends AbstractCache
{
    private PDO $pdo;
    private string $cacheTable;
    private string $cacheKeyField;
    private string $cacheValueField;
    private string $cacheExpiresAt;

    public function __construct(
        PDO $pdo,
        ?string $cacheTable = 'cache',
        ?string $cacheKeyField = 'cache_key',
        ?string $cacheValueField = 'cache_value',
        ?string $cacheExpiresAt = 'expires_at',
        ?string $namespace = null
    ) {
        parent::__construct($namespace);
        $this->pdo = $pdo;
        $this->cacheTable = $cacheTable ?? 'cache';
        $this->cacheKeyField = $cacheKeyField ?? 'cache_key';
        $this->cacheValueField = $cacheValueField ?? 'cache_value';
        $this->cacheExpiresAt = $cacheExpiresAt ?? 'expires_at';
    }

    /**
     * Get the underlying PDO connection.
     *
     * Use cases:
     * - Connection sharing between Domain instances (SharedResource)
     * - Health monitoring and connection status checks
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws \JardisAdapter\Cache\Exception\InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $hashedKey = $this->hash($key);

            $sql = "SELECT {$this->cacheValueField}, {$this->cacheExpiresAt}
                    FROM {$this->cacheTable}
                    WHERE {$this->cacheKeyField} = :key";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':key', $hashedKey, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return $default;
            }

            $expiresAt = $result[$this->cacheExpiresAt];
            if ($expiresAt !== null && $expiresAt <= time()) {
                $this->delete($key);
                return $default;
            }

            return $this->decode($result[$this->cacheValueField]);
        } catch (PDOException $e) {
            return $default;
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|\DateInterval|null $ttl
     * @return bool
     * @throws \JardisAdapter\Cache\Exception\InvalidArgumentException
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        try {
            $expiresAt = $this->ttl($ttl);

            if ($expiresAt === 0) {
                $this->delete($key);
                return true;
            }

            $hashedKey = $this->hash($key);
            $encodedValue = $this->encode($value);

            // Note: REPLACE INTO is SQLite/MySQL specific. PostgreSQL would need
            // INSERT ... ON CONFLICT DO UPDATE. Add a dedicated adapter if needed.
            $sql = "REPLACE INTO {$this->cacheTable}
                    ({$this->cacheKeyField}, {$this->cacheValueField}, {$this->cacheExpiresAt})
                    VALUES (:key, :value, :expires_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':key', $hashedKey, PDO::PARAM_STR);
            $stmt->bindParam(':value', $encodedValue, PDO::PARAM_STR);

            if ($expiresAt !== null) {
                $stmt->bindParam(':expires_at', $expiresAt, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':expires_at', null, PDO::PARAM_NULL);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return bool
     * @throws \JardisAdapter\Cache\Exception\InvalidArgumentException
     */
    public function delete(string $key): bool
    {
        try {
            $hashedKey = $this->hash($key);

            $sql = "DELETE FROM {$this->cacheTable} WHERE {$this->cacheKeyField} = :key";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':key', $hashedKey, PDO::PARAM_STR);

            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        try {
            $namespace = $this->namespace();

            if (empty($namespace)) {
                $sql = "DELETE FROM {$this->cacheTable}";
                $stmt = $this->pdo->prepare($sql);
            } else {
                $sql = "DELETE FROM {$this->cacheTable} WHERE {$this->cacheKeyField} LIKE :namespace";
                $stmt = $this->pdo->prepare($sql);
                $namespacePattern = $namespace . '%';
                $stmt->bindParam(':namespace', $namespacePattern, PDO::PARAM_STR);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return bool
     * @throws \JardisAdapter\Cache\Exception\InvalidArgumentException
     */
    public function has(string $key): bool
    {
        try {
            $hashedKey = $this->hash($key);

            $sql = "SELECT {$this->cacheExpiresAt}
                    FROM {$this->cacheTable}
                    WHERE {$this->cacheKeyField} = :key";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':key', $hashedKey, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return false;
            }

            $expiresAt = $result[$this->cacheExpiresAt];
            if ($expiresAt !== null && $expiresAt <= time()) {
                $this->delete($key);
                return false;
            }

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function cleanExpired(): bool
    {
        try {
            $currentTime = time();

            $sql = "DELETE FROM {$this->cacheTable} 
                    WHERE {$this->cacheExpiresAt} IS NOT NULL 
                    AND {$this->cacheExpiresAt} <= :current_time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}

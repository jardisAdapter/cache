<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use Closure;
use Redis;
use RedisException;

/**
 * Redis-based cache implementation.
 *
 * Takes either a ready Redis handle or a provider `Closure(): Redis`. A provider
 * is invoked lazily on the first cache access (including getConnection()) and its
 * result is reused afterwards; construction never calls it. An exception thrown by
 * the provider is not swallowed by the graceful degradation of the cache methods,
 * it reaches the caller unchanged (and the provider is called again on the next access).
 */
class CacheRedis extends AbstractCache
{
    /** @var Redis|Closure(): Redis */
    private Redis|Closure $connection;

    /**
     * @param Redis|Closure(): Redis $redis connected handle or lazy provider
     */
    public function __construct(Redis|Closure $redis, ?string $namespace = null)
    {
        parent::__construct($namespace);
        $this->connection = $redis;
    }

    /**
     * Get the underlying Redis connection.
     *
     * Use cases:
     * - Connection sharing between Domain instances (SharedResource)
     * - Health monitoring and connection status checks
     */
    public function getConnection(): Redis
    {
        $connection = $this->connection;
        if ($connection instanceof Closure) {
            $connection = $connection();
            $this->connection = $connection;
        }

        return $connection;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws \JardisAdapter\Cache\Exception\InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $redis = $this->getConnection();
        try {
            $hashedKey = $this->hash($key);
            $result = $redis->get($hashedKey);

            return $result !== false ? $this->decode($result) : $default;
        } catch (RedisException $e) {
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
        $redis = $this->getConnection();
        try {
            $ttlValue = $this->ttl($ttl);

            if ($ttlValue === 0) {
                $this->delete($key);
                return true;
            }

            $hashedKey = $this->hash($key);
            $encodedValue = $this->encode($value);

            if ($ttlValue !== null) {
                // Calculate seconds from now
                $seconds = max(1, $ttlValue - time());
                return $redis->setex($hashedKey, $seconds, $encodedValue);
            }

            return $redis->set($hashedKey, $encodedValue);
        } catch (RedisException $e) {
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
        $redis = $this->getConnection();
        try {
            $hashedKey = $this->hash($key);
            $result = $redis->del($hashedKey);

            return $result >= 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $redis = $this->getConnection();
        try {
            $namespace = $this->namespace();

            // If no namespace is set, clear all keys (dangerous!)
            if (empty($namespace)) {
                return $redis->flushDB();
            }

            // Clear only keys with the specific namespace
            $iterator = null;
            $pattern = $namespace . '*';

            do {
                $keys = $redis->scan($iterator, $pattern, 100);

                if ($keys !== false && count($keys) > 0) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);

            return true;
        } catch (RedisException $e) {
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
        $redis = $this->getConnection();
        try {
            $hashedKey = $this->hash($key);
            return $redis->exists($hashedKey) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }
}

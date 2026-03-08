<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use Exception;
use Redis;
use RedisException;

/**
 * Redis-based cache implementation.
 */
class CacheRedis extends AbstractCache
{
    public const LAYER_NAME = 'redis';

    private Redis $redis;

    public function __construct(Redis $redis, ?string $namespace = null)
    {
        $this->redis = $redis;
        $this->setNamespace($namespace);
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
        return $this->redis;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws Exception
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $hashedKey = $this->hash($key);
            $result = $this->redis->get($hashedKey);

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
     * @throws Exception
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        try {
            $hashedKey = $this->hash($key);
            $encodedValue = $this->encode($value);
            $ttlValue = $this->ttl($ttl);

            if ($ttlValue !== null) {
                // Calculate seconds from now
                $seconds = max(1, $ttlValue - time());
                return $this->redis->setex($hashedKey, $seconds, $encodedValue);
            }

            return $this->redis->set($hashedKey, $encodedValue);
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function delete(string $key): bool
    {
        try {
            $hashedKey = $this->hash($key);
            $result = $this->redis->del($hashedKey);

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
        try {
            $namespace = $this->namespace();

            // If no namespace is set, clear all keys (dangerous!)
            if (empty($namespace)) {
                return $this->redis->flushDB();
            }

            // Clear only keys with the specific namespace
            $iterator = null;
            $pattern = $namespace . '*';

            do {
                $keys = $this->redis->scan($iterator, $pattern, 100);

                if ($keys !== false && count($keys) > 0) {
                    $this->redis->del($keys);
                }
            } while ($iterator > 0);

            return true;
        } catch (RedisException $e) {
            error_log('Failed to clear Redis cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function has(string $key): bool
    {
        try {
            $hashedKey = $this->hash($key);
            return $this->redis->exists($hashedKey) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }
}

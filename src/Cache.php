<?php

declare(strict_types=1);

namespace JardisAdapter\Cache;

use JardisAdapter\Cache\Adapter\CacheNull;
use Psr\SimpleCache\CacheInterface;

/**
 * Multi-layer cache implementation using a Chain of Responsibility pattern.
 *
 * Immutable after construction — all layers are set via constructor.
 * Each layer manages its own namespace via its own constructor.
 *
 * Example:
 * ```php
 * // Fast request cache
 * $requestCache = new Cache([new CacheMemory('request'), new CacheApcu('request')]);
 *
 * // Persistent cache
 * $persistentCache = new Cache([
 *     new CacheRedis($redis, 'persistent'),
 *     new CacheDatabase($pdo, namespace: 'persistent'),
 * ]);
 *
 * // PSR-16: writes to all layers
 * $requestCache->set('key', 'value');
 * ```
 */
class Cache implements CacheInterface
{
    /** @var array<int, CacheInterface> */
    private array $cache;

    /**
     * @param array<int, CacheInterface> $caches Cache layers in priority order (first = L1, last = LN).
     *                                           Empty array results in a NullCache (no-op).
     */
    public function __construct(array $caches = [])
    {
        $this->cache = $caches !== [] ? array_values($caches) : [new CacheNull()];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $foundAtIndex = null;
        $value = $default;

        // Search through cache layers using has() to correctly detect cached null values
        foreach ($this->cache as $index => $cache) {
            if ($cache->has($key)) {
                $value = $cache->get($key, $default);
                $foundAtIndex = $index;
                break;
            }
        }

        // Write-through: Populate higher-level caches.
        // Note: TTL is not propagated — the value lives in upper layers without expiration.
        // This is a known trade-off: extracting remaining TTL would break adapter abstraction.
        // Practical impact is low (L1=CacheMemory lives one request, CacheApcu has own eviction).
        if ($foundAtIndex !== null && $foundAtIndex > 0) {
            for ($i = 0; $i < $foundAtIndex; $i++) {
                $this->cache[$i]->set($key, $value);
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->applyToAllCaches(fn($cache) => $cache->set($key, $value, $ttl));
    }

    public function delete(string $key): bool
    {
        return $this->applyToAllCaches(fn($cache) => $cache->delete($key));
    }

    public function clear(): bool
    {
        return $this->applyToAllCaches(fn($cache) => $cache->clear());
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        foreach ($this->cache as $cache) {
            if ($cache->has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applies the given callback to all cache layers.
     *
     * @param callable $callback A callback function to be executed on each cache.
     * @return bool True if all operations succeeded, false otherwise.
     */
    private function applyToAllCaches(callable $callback): bool
    {
        $success = true;
        foreach ($this->cache as $cache) {
            if (!$callback($cache)) {
                $success = false;
            }
        }
        return $success;
    }

    /** @return array<int, CacheInterface> */
    public function getLayers(): array
    {
        return $this->cache;
    }
}

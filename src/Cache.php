<?php

declare(strict_types=1);

namespace JardisAdapter\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Multi-layer cache implementation using a Chain of Responsibility pattern.
 *
 * Enables cascading cache lookups (L1 → L2 → L3) with automatic cache population
 * for optimal performance across multiple cache layers.
 *
 * Supports named layers via PHP 8 named arguments for targeted cache operations.
 *
 * Example:
 * ```php
 * $cache = new Cache(
 *     memory:   new CacheMemory(),      // L1: Fast in-memory
 *     redis:    new CacheRedis($redis), // L2: Medium-speed persistent
 *     database: new CacheDatabase($pdo) // L3: Slow but durable
 * );
 *
 * // PSR-16: writes to all layers
 * $cache->set('key', 'value');
 *
 * // Targeted: writes only to redis
 * $cache->layer('redis')->set('key', 'value', 300);
 * ```
 */
class Cache implements LayerAwareCacheInterface
{
    /** @var array<int, CacheInterface> */
    private array $cache;

    /** @var array<string, CacheInterface> */
    private array $namedLayers = [];

    public function __construct(CacheInterface ...$caches)
    {
        if (count($caches) < 1) {
            throw new \InvalidArgumentException('At least one cache layer required');
        }

        foreach ($caches as $name => $cache) {
            if (is_string($name)) {
                $this->namedLayers[$name] = $cache;
            }
        }

        $this->cache = array_values($caches); // Ensure sequential numeric keys
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $foundAtIndex = null;
        $value = $default;

        // Search through cache layers
        foreach ($this->cache as $index => $cache) {
            $result = $cache->get($key, $default);
            if ($result !== $default) {
                $value = $result;
                $foundAtIndex = $index;
                break;
            }
        }

        // Write-through: Populate higher-level caches
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
        $success = true;
        foreach ($this->cache as $cache) {
            if (!$cache->clear()) {
                $success = false;
            }
        }
        return $success;
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

    /**
     * Returns a specific named cache layer for targeted operations.
     *
     * Named layers are available when the cache is constructed with
     * PHP 8 named arguments.
     *
     * @param string $name The name of the cache layer.
     * @return CacheInterface The cache layer instance.
     * @throws \InvalidArgumentException If the named layer does not exist.
     */
    public function layer(string $name): CacheInterface
    {
        if (!isset($this->namedLayers[$name])) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Cache layer '%s' not found. Available layers: %s",
                    $name,
                    implode(', ', array_keys($this->namedLayers)) ?: 'none (use named arguments in constructor)'
                )
            );
        }

        return $this->namedLayers[$name];
    }
}

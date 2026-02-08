<?php

declare(strict_types=1);

namespace JardisAdapter\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Extends PSR-16 CacheInterface with named layer access.
 *
 * Allows targeted cache operations on specific layers while maintaining
 * full PSR-16 compatibility for standard cache operations.
 *
 * Example:
 * ```php
 * $cache = new Cache(
 *     memory:   new CacheMemory(),
 *     redis:    new CacheRedis($redis),
 *     database: new CacheDatabase($pdo)
 * );
 *
 * // PSR-16: writes to all layers
 * $cache->set('key', 'value');
 *
 * // Targeted: writes only to redis
 * $cache->layer('redis')->set('key', 'value', 300);
 * ```
 */
interface LayerAwareCacheInterface extends CacheInterface
{
    /**
     * Returns a specific named cache layer.
     *
     * The returned instance is a full PSR-16 CacheInterface,
     * enabling targeted operations on a single layer.
     *
     * @param string $name The name of the cache layer.
     * @return CacheInterface The cache layer instance.
     * @throws \InvalidArgumentException If the named layer does not exist.
     */
    public function layer(string $name): CacheInterface;
}

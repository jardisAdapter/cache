<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

/**
 * Null cache implementation (Null Object Pattern).
 *
 * Stores nothing, returns defaults. Used as internal default
 * when no cache layers have been added to Cache.
 */
class CacheNull extends AbstractCache
{
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        return false;
    }
}

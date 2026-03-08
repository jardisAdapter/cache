<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use Exception;

/**
 * In-memory cache implementation.
 */
class CacheMemory extends AbstractCache
{
    public const LAYER_NAME = 'memory';

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(?string $namespace = null)
    {
        $this->setNamespace($namespace);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws Exception
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $hashedKey = $this->hash($key);

        if (!isset($this->cache[$hashedKey])) {
            return $default;
        }

        $result = $this->cache[$hashedKey];

        if ($this->isExpired($result)) {
            unset($this->cache[$hashedKey]);
            return $default;
        }

        return is_array($result) && array_key_exists('value', $result) ? $result['value'] : $result;
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
        $hashedKey = $this->hash($key);

        $this->cache[$hashedKey] = [
            'value' => $value,
            'ttl' => $this->ttl($ttl)
        ];

        return true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function delete(string $key): bool
    {
        $hashedKey = $this->hash($key);

        if (isset($this->cache[$hashedKey])) {
            unset($this->cache[$hashedKey]);
            return true;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function has(string $key): bool
    {
        $hashedKey = $this->hash($key);

        if (!isset($this->cache[$hashedKey])) {
            return false;
        }

        if ($this->isExpired($this->cache[$hashedKey])) {
            unset($this->cache[$hashedKey]);
            return false;
        }

        return true;
    }
}

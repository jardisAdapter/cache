<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use DateInterval;
use DateTime;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Abstract base class providing common caching functionality for cache implementations.
 */
abstract class AbstractCache implements CacheInterface
{
    private string $namespace = '';

    /**
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
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
     * @param int|\DateInterval|null $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    protected function namespace(): string
    {
        return $this->namespace;
    }

    protected function setNamespace(?string $namespace = null): void
    {
        $this->namespace = trim($namespace ?? '');
    }

    protected function hash(string $key): string
    {
        if (trim($key) === '') {
            throw new Exception('Key must be a non-empty string.');
        }

        return $this->namespace() . hash('sha256', $key);
    }

    protected function ttl(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl) && $ttl > 0) {
            return time() + $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTime())->add($ttl)->getTimestamp();
        }

        return null;
    }

    protected function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return serialize($value);
        }
    }

    protected function decode(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        try {
            // Try JSON decoding first (handles objects, arrays, strings, numbers, booleans, null)
            if (preg_match('/^\s*([{\["tfn]|-?\d)/', $value)) {
                $result = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return $result;
            }

            // Try unserialization
            if (preg_match('/^([aOsid]):/', $value)) {
                $result = @unserialize($value);
                if ($result !== false || $value === 'b:0;') {
                    return $result;
                }
            }
        } catch (Exception $e) {
            // Ignore decoding errors and return original value
        }

        return $value;
    }

    protected function isExpired(mixed $result): bool
    {
        return is_array($result)
            && isset($result['ttl'])
            && $result['ttl'] != null
            && $result['ttl'] <= time();
    }
}

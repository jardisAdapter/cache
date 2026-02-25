<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Adapter;

use Exception;

/**
 * APCu-based cache implementation.
 */
class CacheApcu extends AbstractCache
{
    public const LAYER_NAME = 'apcu';

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

        $result = apcu_fetch($hashedKey, $success);

        if (!$success) {
            return $default;
        }

        if ($this->isExpired($result)) {
            apcu_delete($hashedKey);
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
        $ttlValue = $this->ttl($ttl);

        $data = ['value' => $value, 'ttl' => $ttlValue];

        $apcuTtl = 0;
        if ($ttlValue !== null) {
            $apcuTtl = max(0, $ttlValue - time());
        }

        return apcu_store($hashedKey, $data, $apcuTtl);
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function delete(string $key): bool
    {
        $hashedKey = $this->hash($key);

        if (!apcu_exists($hashedKey)) {
            return true;
        }

        return apcu_delete($hashedKey);
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $namespace = $this->namespace();

        // If no namespace is set, clear entire cache (dangerous!)
        if (empty($namespace)) {
            return apcu_clear_cache();
        }

        // Clear only keys with the specific namespace
        $info = apcu_cache_info();
        if (!isset($info['cache_list'])) {
            return true;
        }

        $pattern = '/^' . preg_quote($namespace, '/') . '/';
        foreach ($info['cache_list'] as $entry) {
            if (isset($entry['info']) && preg_match($pattern, $entry['info'])) {
                apcu_delete($entry['info']);
            }
        }

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

        $result = apcu_fetch($hashedKey, $success);

        if (!$success) {
            return false;
        }

        if ($this->isExpired($result)) {
            apcu_delete($hashedKey);
            return false;
        }

        return true;
    }
}

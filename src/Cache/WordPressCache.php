<?php

declare(strict_types=1);

namespace LicensesOS\Cache;

/**
 * WordPress transients cache adapter.
 *
 * Uses WordPress transients API for storing license validation results.
 * Requires WordPress to be loaded.
 */
class WordPressCache implements CacheInterface
{
    private string $prefix;

    /**
     * @param string $prefix Prefix for all cache keys (e.g., your plugin slug)
     */
    public function __construct(string $prefix = 'licensesos')
    {
        $this->prefix = $prefix;
    }

    public function get(string $key): mixed
    {
        if (!function_exists('get_transient')) {
            return null;
        }

        $value = get_transient($this->prefixKey($key));

        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        if (!function_exists('set_transient')) {
            return false;
        }

        return set_transient($this->prefixKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        if (!function_exists('delete_transient')) {
            return false;
        }

        return delete_transient($this->prefixKey($key));
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . '_' . $key;
    }
}

<?php

declare(strict_types=1);

namespace LicensesOS\Cache;

/**
 * Simple cache interface for storing license validation results.
 *
 * Implementations can use WordPress transients, file storage, Redis, etc.
 */
interface CacheInterface
{
    /**
     * Get a value from the cache.
     *
     * @param string $key The cache key
     * @return mixed|null The cached value or null if not found/expired
     */
    public function get(string $key): mixed;

    /**
     * Store a value in the cache.
     *
     * @param string $key The cache key
     * @param mixed $value The value to store
     * @param int $ttl Time to live in seconds
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int $ttl): bool;

    /**
     * Delete a value from the cache.
     *
     * @param string $key The cache key
     * @return bool True on success
     */
    public function delete(string $key): bool;
}

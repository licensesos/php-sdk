<?php

declare(strict_types=1);

namespace LicensesOS\Cache;

use LicensesOS\LicensesOsClient;
use LicensesOS\Results\ValidationResult;
use LicensesOS\Exceptions\ApiException;
use LicensesOS\Exceptions\NetworkException;

/**
 * High-level license caching with offline grace period support.
 *
 * This class wraps the LicensesOsClient and provides intelligent caching:
 * - Caches validation results to reduce API calls
 * - Supports offline grace periods for better user experience
 * - Automatically refreshes cache when stale
 *
 * Usage:
 * ```php
 * $client = new LicensesOsClient('your_api_key');
 * $cache = new WordPressCache('my_plugin');
 * $licenseCache = new LicenseCache($client, $cache);
 *
 * $result = $licenseCache->validate($licenseKey, $domain);
 * if ($licenseCache->shouldAllowPremium($licenseKey)) {
 *     // Enable premium features
 * }
 * ```
 */
class LicenseCache
{
    private LicensesOsClient $client;
    private CacheInterface $cache;

    /**
     * Cache TTL in seconds (12 hours).
     */
    private int $cacheTtl;

    /**
     * Grace period in seconds (48 hours).
     * Features remain active during grace period even if API is unreachable.
     */
    private int $gracePeriod;

    /**
     * @param LicensesOsClient $client The API client
     * @param CacheInterface $cache Cache storage implementation
     * @param int $cacheTtl Cache TTL in seconds (default: 12 hours)
     * @param int $gracePeriod Grace period in seconds (default: 48 hours)
     */
    public function __construct(
        LicensesOsClient $client,
        CacheInterface $cache,
        int $cacheTtl = 43200,
        int $gracePeriod = 172800
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->gracePeriod = $gracePeriod;
    }

    /**
     * Validate a license with caching support.
     *
     * Returns cached result if fresh, otherwise calls API and caches the result.
     * If API is unreachable but cache exists within grace period, returns cached result.
     *
     * @param string $licenseKey The license key
     * @param string $identifier The domain or device identifier
     * @param array $metadata Optional metadata
     * @param bool $forceRefresh Force a fresh API call
     * @return ValidationResult
     * @throws ApiException|NetworkException if API fails and no valid cache
     */
    public function validate(
        string $licenseKey,
        string $identifier,
        array $metadata = [],
        bool $forceRefresh = false
    ): ValidationResult {
        $cacheKey = $this->buildCacheKey($licenseKey, $identifier);
        $cached = $this->getCachedValidation($cacheKey);

        // Return fresh cache if available and not forcing refresh
        if ($cached !== null && !$forceRefresh && $this->isCacheFresh($cached)) {
            return new ValidationResult($cached['data']);
        }

        try {
            // Call API
            $result = $this->client->validate($licenseKey, $identifier, $metadata);

            // Cache the result
            $this->cacheValidation($cacheKey, $result);

            return $result;
        } catch (NetworkException $e) {
            // Network error - use cached result if within grace period
            if ($cached !== null && $this->isWithinGracePeriod($cached)) {
                return new ValidationResult($cached['data']);
            }

            throw $e;
        }
    }

    /**
     * Determine if premium features should be allowed.
     *
     * Uses cached validation state with grace period for offline tolerance.
     *
     * @param string $licenseKey The license key
     * @param string|null $identifier Optional identifier (uses stored one if null)
     * @return bool True if premium features should be enabled
     */
    public function shouldAllowPremium(string $licenseKey, ?string $identifier = null): bool
    {
        $cacheKey = $identifier !== null
            ? $this->buildCacheKey($licenseKey, $identifier)
            : $this->buildLicenseCacheKey($licenseKey);

        $cached = $this->getCachedValidation($cacheKey);

        if ($cached === null) {
            return false;
        }

        // Check if status is active
        $status = $cached['data']['status'] ?? 'unknown';
        if ($status !== 'active') {
            return false;
        }

        // Allow if within TTL + grace period
        return $this->isWithinGracePeriod($cached);
    }

    /**
     * Get the cached validation state.
     *
     * @param string $licenseKey The license key
     * @param string|null $identifier Optional identifier
     * @return array|null Cached state with 'status', 'cached_at', etc.
     */
    public function getCachedState(string $licenseKey, ?string $identifier = null): ?array
    {
        $cacheKey = $identifier !== null
            ? $this->buildCacheKey($licenseKey, $identifier)
            : $this->buildLicenseCacheKey($licenseKey);

        $cached = $this->getCachedValidation($cacheKey);

        if ($cached === null) {
            return null;
        }

        return [
            'status' => $cached['data']['status'] ?? 'unknown',
            'valid' => $cached['data']['valid'] ?? false,
            'expires_at' => $cached['data']['expires_at'] ?? null,
            'entitlements' => $cached['data']['entitlements'] ?? [],
            'cached_at' => $cached['cached_at'],
            'is_fresh' => $this->isCacheFresh($cached),
            'is_within_grace' => $this->isWithinGracePeriod($cached),
        ];
    }

    /**
     * Clear cached validation for a license.
     *
     * @param string $licenseKey The license key
     * @param string|null $identifier Optional identifier
     * @return bool True on success
     */
    public function clearCache(string $licenseKey, ?string $identifier = null): bool
    {
        $cacheKey = $identifier !== null
            ? $this->buildCacheKey($licenseKey, $identifier)
            : $this->buildLicenseCacheKey($licenseKey);

        return $this->cache->delete($cacheKey);
    }

    /**
     * Check if validation needs refresh (cache is stale).
     *
     * @param string $licenseKey The license key
     * @param string|null $identifier Optional identifier
     * @return bool True if cache should be refreshed
     */
    public function needsRefresh(string $licenseKey, ?string $identifier = null): bool
    {
        $cacheKey = $identifier !== null
            ? $this->buildCacheKey($licenseKey, $identifier)
            : $this->buildLicenseCacheKey($licenseKey);

        $cached = $this->getCachedValidation($cacheKey);

        if ($cached === null) {
            return true;
        }

        return !$this->isCacheFresh($cached);
    }

    /**
     * Store activation identifier for later use.
     *
     * @param string $licenseKey The license key
     * @param string $identifier The identifier that was activated
     */
    public function storeActivationIdentifier(string $licenseKey, string $identifier): void
    {
        $key = 'activation_' . $this->hashKey($licenseKey);
        $this->cache->set($key, $identifier, $this->cacheTtl + $this->gracePeriod);
    }

    /**
     * Get stored activation identifier.
     *
     * @param string $licenseKey The license key
     * @return string|null The stored identifier or null
     */
    public function getStoredIdentifier(string $licenseKey): ?string
    {
        $key = 'activation_' . $this->hashKey($licenseKey);
        $value = $this->cache->get($key);

        return is_string($value) ? $value : null;
    }

    private function buildCacheKey(string $licenseKey, string $identifier): string
    {
        return 'validation_' . $this->hashKey($licenseKey . ':' . $identifier);
    }

    private function buildLicenseCacheKey(string $licenseKey): string
    {
        // Try to find cached validation for this license
        $identifier = $this->getStoredIdentifier($licenseKey);
        if ($identifier !== null) {
            return $this->buildCacheKey($licenseKey, $identifier);
        }

        return 'validation_' . $this->hashKey($licenseKey);
    }

    private function hashKey(string $key): string
    {
        return substr(hash('sha256', $key), 0, 16);
    }

    private function getCachedValidation(string $cacheKey): ?array
    {
        $cached = $this->cache->get($cacheKey);

        if (!is_array($cached) || !isset($cached['data'], $cached['cached_at'])) {
            return null;
        }

        return $cached;
    }

    private function cacheValidation(string $cacheKey, ValidationResult $result): void
    {
        $data = [
            'data' => $result->toArray(),
            'cached_at' => time(),
        ];

        // Store for TTL + grace period to allow grace period checks
        $this->cache->set($cacheKey, $data, $this->cacheTtl + $this->gracePeriod);

        // Also store by license key only for quick lookups
        $licenseKey = $this->extractLicenseKeyFromCacheKey($cacheKey);
        if ($licenseKey !== null) {
            $this->cache->set(
                'validation_' . $this->hashKey($licenseKey),
                $data,
                $this->cacheTtl + $this->gracePeriod
            );
        }
    }

    private function extractLicenseKeyFromCacheKey(string $cacheKey): ?string
    {
        // Cache key format: validation_{hash}
        // We can't reverse the hash, but we stored the identifier separately
        return null;
    }

    private function isCacheFresh(array $cached): bool
    {
        $age = time() - $cached['cached_at'];
        return $age <= $this->cacheTtl;
    }

    private function isWithinGracePeriod(array $cached): bool
    {
        $age = time() - $cached['cached_at'];
        return $age <= ($this->cacheTtl + $this->gracePeriod);
    }
}

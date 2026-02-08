<?php

declare(strict_types=1);

namespace LicenseOS\WordPress;

use LicenseOS\LicenseOsClient;
use LicenseOS\Cache\LicenseCache;
use LicenseOS\Cache\WordPressCache;
use LicenseOS\Results\ValidationResult;
use LicenseOS\Results\ActivationResult;
use LicenseOS\Exceptions\ApiException;
use LicenseOS\Exceptions\NetworkException;

/**
 * WordPress License Manager integration class.
 *
 * Provides a complete license management solution for WordPress plugins/themes.
 * Handles license storage, validation caching, activation, and feature gating.
 *
 * Usage:
 * ```php
 * class MyPluginLicense extends LicenseManager {
 *     protected function getApiKey(): string {
 *         return 'your_api_key_here';
 *     }
 *
 *     protected function getOptionPrefix(): string {
 *         return 'my_plugin';
 *     }
 * }
 *
 * $license = new MyPluginLicense();
 *
 * // In your settings page
 * if ($license->activate($_POST['license_key'])) {
 *     echo 'License activated!';
 * }
 *
 * // Check features anywhere
 * if ($license->isPremium()) {
 *     // Enable premium features
 * }
 * ```
 */
abstract class LicenseManager
{
    protected ?LicenseOsClient $client = null;
    protected ?LicenseCache $licenseCache = null;
    protected ?WordPressCache $cache = null;

    /**
     * Get the API key for your application.
     * Override this method to return your API key.
     */
    abstract protected function getApiKey(): string;

    /**
     * Get the option prefix for storing license data.
     * Override this method to return your plugin's unique prefix.
     */
    abstract protected function getOptionPrefix(): string;

    /**
     * Get the API base URL (optional override).
     */
    protected function getApiBaseUrl(): string
    {
        return 'https://api.licenseos.com';
    }

    /**
     * Get the cache TTL in seconds (default: 12 hours).
     */
    protected function getCacheTtl(): int
    {
        return 43200;
    }

    /**
     * Get the grace period in seconds (default: 48 hours).
     */
    protected function getGracePeriod(): int
    {
        return 172800;
    }

    /**
     * Get the site identifier (domain).
     */
    public function getSiteIdentifier(): string
    {
        $siteUrl = get_site_url();
        return LicenseOsClient::normalizeDomain($siteUrl);
    }

    /**
     * Get the stored license key.
     */
    public function getLicenseKey(): ?string
    {
        $key = get_option($this->getOptionPrefix() . '_license_key');
        return is_string($key) && !empty($key) ? $key : null;
    }

    /**
     * Store a license key.
     */
    public function setLicenseKey(string $licenseKey): void
    {
        update_option($this->getOptionPrefix() . '_license_key', $licenseKey);
    }

    /**
     * Clear the stored license key and cached data.
     */
    public function clearLicenseKey(): void
    {
        delete_option($this->getOptionPrefix() . '_license_key');
        delete_option($this->getOptionPrefix() . '_license_status');
        delete_option($this->getOptionPrefix() . '_license_data');

        $licenseKey = $this->getLicenseKey();
        if ($licenseKey !== null) {
            $this->getLicenseCache()->clearCache($licenseKey, $this->getSiteIdentifier());
        }
    }

    /**
     * Get the cached license status.
     */
    public function getStatus(): string
    {
        $status = get_option($this->getOptionPrefix() . '_license_status');
        return is_string($status) ? $status : 'unknown';
    }

    /**
     * Get the cached license data.
     *
     * @return array{status: string, expires_at: ?string, entitlements: array, cached_at: int}|null
     */
    public function getLicenseData(): ?array
    {
        $data = get_option($this->getOptionPrefix() . '_license_data');
        return is_array($data) ? $data : null;
    }

    /**
     * Check if the license allows premium features.
     *
     * Uses cached validation with grace period for offline tolerance.
     */
    public function isPremium(): bool
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey === null) {
            return false;
        }

        return $this->getLicenseCache()->shouldAllowPremium(
            $licenseKey,
            $this->getSiteIdentifier()
        );
    }

    /**
     * Check if the license has a specific entitlement.
     *
     * @param string $entitlement The entitlement key to check
     */
    public function hasEntitlement(string $entitlement): bool
    {
        if (!$this->isPremium()) {
            return false;
        }

        $data = $this->getLicenseData();
        if ($data === null) {
            return false;
        }

        return isset($data['entitlements'][$entitlement]);
    }

    /**
     * Get an entitlement value.
     *
     * @param string $entitlement The entitlement key
     * @param mixed $default Default value if not found
     */
    public function getEntitlement(string $entitlement, mixed $default = null): mixed
    {
        if (!$this->isPremium()) {
            return $default;
        }

        $data = $this->getLicenseData();
        if ($data === null) {
            return $default;
        }

        return $data['entitlements'][$entitlement] ?? $default;
    }

    /**
     * Validate the current license.
     *
     * @param bool $forceRefresh Force a fresh API call
     * @return ValidationResult|null Null if no license key is set
     */
    public function validate(bool $forceRefresh = false): ?ValidationResult
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey === null) {
            return null;
        }

        try {
            $result = $this->getLicenseCache()->validate(
                $licenseKey,
                $this->getSiteIdentifier(),
                $this->getValidationMetadata(),
                $forceRefresh
            );

            $this->updateStoredLicenseData($result);

            return $result;
        } catch (ApiException|NetworkException $e) {
            $this->logError('Validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Activate a license key for this site.
     *
     * @param string $licenseKey The license key to activate
     * @return ActivationResult
     * @throws ApiException|NetworkException on failure
     */
    public function activate(string $licenseKey): ActivationResult
    {
        $result = $this->getClient()->activate(
            $licenseKey,
            $this->getSiteIdentifier(),
            $this->getValidationMetadata()
        );

        if ($result->isActivated()) {
            $this->setLicenseKey($licenseKey);
            $this->getLicenseCache()->storeActivationIdentifier(
                $licenseKey,
                $this->getSiteIdentifier()
            );

            // Validate to populate cache and stored data
            $this->validate(true);
        }

        return $result;
    }

    /**
     * Deactivate the current license from this site.
     *
     * @return bool True if successfully deactivated
     */
    public function deactivate(): bool
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey === null) {
            return false;
        }

        try {
            $result = $this->getClient()->deactivate(
                $licenseKey,
                $this->getSiteIdentifier()
            );

            if ($result->isDeactivated()) {
                $this->clearLicenseKey();
                return true;
            }

            return false;
        } catch (ApiException|NetworkException $e) {
            $this->logError('Deactivation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the cached license needs refresh.
     */
    public function needsRefresh(): bool
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey === null) {
            return false;
        }

        return $this->getLicenseCache()->needsRefresh(
            $licenseKey,
            $this->getSiteIdentifier()
        );
    }

    /**
     * Get metadata to include with validation/activation requests.
     * Override to add custom metadata.
     */
    protected function getValidationMetadata(): array
    {
        global $wp_version;

        return [
            'site_url' => get_site_url(),
            'wp_version' => $wp_version ?? 'unknown',
            'php_version' => PHP_VERSION,
            'plugin_version' => $this->getPluginVersion(),
        ];
    }

    /**
     * Get the plugin/theme version.
     * Override to return your actual version.
     */
    protected function getPluginVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Log an error message.
     * Override to use your preferred logging method.
     */
    protected function logError(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[' . $this->getOptionPrefix() . '] ' . $message);
        }
    }

    /**
     * Update stored license data from validation result.
     */
    protected function updateStoredLicenseData(ValidationResult $result): void
    {
        update_option($this->getOptionPrefix() . '_license_status', $result->status);
        update_option($this->getOptionPrefix() . '_license_data', [
            'status' => $result->status,
            'valid' => $result->valid,
            'expires_at' => $result->expiresAt,
            'updates_until' => $result->updatesUntil,
            'entitlements' => $result->entitlements,
            'cached_at' => time(),
        ]);
    }

    /**
     * Get the API client instance.
     */
    protected function getClient(): LicenseOsClient
    {
        if ($this->client === null) {
            $this->client = new LicenseOsClient(
                $this->getApiKey(),
                $this->getApiBaseUrl()
            );
        }

        return $this->client;
    }

    /**
     * Get the cache instance.
     */
    protected function getCache(): WordPressCache
    {
        if ($this->cache === null) {
            $this->cache = new WordPressCache($this->getOptionPrefix());
        }

        return $this->cache;
    }

    /**
     * Get the license cache instance.
     */
    protected function getLicenseCache(): LicenseCache
    {
        if ($this->licenseCache === null) {
            $this->licenseCache = new LicenseCache(
                $this->getClient(),
                $this->getCache(),
                $this->getCacheTtl(),
                $this->getGracePeriod()
            );
        }

        return $this->licenseCache;
    }
}

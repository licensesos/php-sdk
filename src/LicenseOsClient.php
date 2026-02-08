<?php

declare(strict_types=1);

namespace LicenseOS;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LicenseOS\Exceptions\ApiException;
use LicenseOS\Exceptions\NetworkException;
use LicenseOS\Results\ActivationResult;
use LicenseOS\Results\ActivationsResult;
use LicenseOS\Results\DeactivationResult;
use LicenseOS\Results\ValidationResult;

class LicenseOsClient
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.licenseos.com'
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Validate a license key for a given identifier (domain/device).
     */
    public function validate(
        string $licenseKey,
        string $identifier,
        array $metadata = []
    ): ValidationResult {
        $payload = [
            'license_key' => $licenseKey,
            'identifier' => self::normalizeDomain($identifier),
        ];

        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->request('POST', '/api/v1/licenses/validate', $payload);

        return new ValidationResult($response);
    }

    /**
     * Activate a license for a given identifier.
     */
    public function activate(
        string $licenseKey,
        string $identifier,
        array $metadata = []
    ): ActivationResult {
        $payload = [
            'license_key' => $licenseKey,
            'identifier' => self::normalizeDomain($identifier),
        ];

        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->request('POST', '/api/v1/activations/activate', $payload);

        return new ActivationResult($response);
    }

    /**
     * Deactivate a license for a given identifier.
     */
    public function deactivate(
        string $licenseKey,
        string $identifier
    ): DeactivationResult {
        $payload = [
            'license_key' => $licenseKey,
            'identifier' => self::normalizeDomain($identifier),
        ];

        $response = $this->request('POST', '/api/v1/activations/deactivate', $payload);

        return new DeactivationResult($response);
    }

    /**
     * List all activations for a license.
     */
    public function listActivations(string $licenseKey): ActivationsResult
    {
        $response = $this->request('GET', "/api/v1/licenses/{$licenseKey}/activations");

        return new ActivationsResult($response);
    }

    /**
     * Normalize a domain/URL to a consistent format.
     */
    public static function normalizeDomain(string $input): string
    {
        // Lowercase and trim
        $domain = strtolower(trim($input));

        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $domain);

        // Remove trailing slash and path
        $domain = preg_replace('#/.*$#', '', $domain);

        // Remove port
        $domain = preg_replace('#:\d+$#', '', $domain);

        // Remove www. prefix
        $domain = preg_replace('#^www\.#', '', $domain);

        // Handle IDN domains
        if (preg_match('/[^\x20-\x7E]/', $domain) && function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }

        return $domain;
    }

    /**
     * Determine if premium features should be allowed based on cached state.
     *
     * @param array $cachedState Array with 'status', 'cached_at' keys
     * @param int $graceTtl Grace period in seconds (default 48 hours)
     */
    public static function shouldAllowPremium(array $cachedState, int $graceTtl = 172800): bool
    {
        $status = $cachedState['status'] ?? null;
        $cachedAt = $cachedState['cached_at'] ?? 0;

        if ($status !== 'active') {
            return false;
        }

        $cacheTtl = 43200; // 12 hours
        $maxAge = $cacheTtl + $graceTtl;
        $age = time() - $cachedAt;

        return $age <= $maxAge;
    }

    /**
     * Make an HTTP request to the API.
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $options = [];

            if (!empty($payload)) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = json_decode($response->getBody()->getContents(), true);

                throw new ApiException(
                    $body['error']['message'] ?? 'API request failed',
                    $body['error']['code'] ?? 'UNKNOWN_ERROR',
                    $response->getStatusCode()
                );
            }

            throw new NetworkException(
                'Failed to connect to LicenseOS API: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}

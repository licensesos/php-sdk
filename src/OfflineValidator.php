<?php

declare(strict_types=1);

namespace LicenseOS;

use LicenseOS\Results\OfflineValidationResult;

class OfflineValidator
{
    private string $publicKeyBytes;

    public function __construct(string $publicKeyBase64)
    {
        $this->publicKeyBytes = base64_decode($publicKeyBase64);
    }

    /**
     * Validate an offline token using the app's Ed25519 public key.
     */
    public function validate(string $token): OfflineValidationResult
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return OfflineValidationResult::invalid('INVALID_TOKEN_FORMAT');
        }

        $payloadJson = $this->base64UrlDecode($parts[0]);
        $signature = $this->base64UrlDecode($parts[1]);

        if (! sodium_crypto_sign_verify_detached($signature, $payloadJson, $this->publicKeyBytes)) {
            return OfflineValidationResult::invalid('INVALID_SIGNATURE');
        }

        $payload = json_decode($payloadJson, true);
        if ($payload === null) {
            return OfflineValidationResult::invalid('INVALID_PAYLOAD');
        }

        if (($payload['exp'] ?? 0) < time()) {
            return OfflineValidationResult::expired($payload);
        }

        return OfflineValidationResult::fromPayload($payload, true);
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

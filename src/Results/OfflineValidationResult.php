<?php

declare(strict_types=1);

namespace LicensesOS\Results;

class OfflineValidationResult
{
    public readonly bool $valid;
    public readonly string $status;
    public readonly ?string $errorCode;
    public readonly ?string $licenseId;
    public readonly array $entitlements;
    public readonly ?string $expiresAt;
    public readonly ?int $activationLimit;
    public readonly int $activationCount;
    public readonly ?string $identifier;
    public readonly bool $activated;
    public readonly int $issuedAt;
    public readonly int $expiresAtTimestamp;
    public readonly int $ttl;
    public readonly int $usesCount;
    public readonly ?int $maxUses;

    private function __construct(
        bool $valid,
        string $status,
        ?string $errorCode,
        ?string $licenseId,
        array $entitlements,
        ?string $expiresAt,
        ?int $activationLimit,
        int $activationCount,
        ?string $identifier,
        bool $activated,
        int $issuedAt,
        int $expiresAtTimestamp,
        int $ttl,
        int $usesCount,
        ?int $maxUses,
    ) {
        $this->valid = $valid;
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->licenseId = $licenseId;
        $this->entitlements = $entitlements;
        $this->expiresAt = $expiresAt;
        $this->activationLimit = $activationLimit;
        $this->activationCount = $activationCount;
        $this->identifier = $identifier;
        $this->activated = $activated;
        $this->issuedAt = $issuedAt;
        $this->expiresAtTimestamp = $expiresAtTimestamp;
        $this->ttl = $ttl;
        $this->usesCount = $usesCount;
        $this->maxUses = $maxUses;
    }

    public static function invalid(string $errorCode): self
    {
        return new self(
            valid: false,
            status: 'unknown',
            errorCode: $errorCode,
            licenseId: null,
            entitlements: [],
            expiresAt: null,
            activationLimit: null,
            activationCount: 0,
            identifier: null,
            activated: false,
            issuedAt: 0,
            expiresAtTimestamp: 0,
            ttl: 0,
            usesCount: 0,
            maxUses: null,
        );
    }

    public static function expired(array $payload): self
    {
        return self::fromPayload($payload, false, 'TOKEN_EXPIRED');
    }

    public static function fromPayload(array $payload, bool $valid, ?string $errorCode = null): self
    {
        return new self(
            valid: $valid,
            status: $payload['status'] ?? 'unknown',
            errorCode: $errorCode,
            licenseId: $payload['license_id'] ?? null,
            entitlements: $payload['entitlements'] ?? [],
            expiresAt: $payload['expires_at'] ?? null,
            activationLimit: $payload['activation_limit'] ?? null,
            activationCount: $payload['activation_count'] ?? 0,
            identifier: $payload['identifier'] ?? null,
            activated: $payload['activated'] ?? false,
            issuedAt: $payload['iat'] ?? 0,
            expiresAtTimestamp: $payload['exp'] ?? 0,
            ttl: $payload['ttl'] ?? 0,
            usesCount: $payload['uses_count'] ?? 0,
            maxUses: $payload['max_uses'] ?? null,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isExpired(): bool
    {
        return $this->expiresAtTimestamp < time();
    }

    public function remainingSeconds(): int
    {
        return max(0, $this->expiresAtTimestamp - time());
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'license_id' => $this->licenseId,
            'entitlements' => $this->entitlements,
            'expires_at' => $this->expiresAt,
            'activation_limit' => $this->activationLimit,
            'activation_count' => $this->activationCount,
            'identifier' => $this->identifier,
            'activated' => $this->activated,
            'issued_at' => $this->issuedAt,
            'expires_at_timestamp' => $this->expiresAtTimestamp,
            'ttl' => $this->ttl,
            'uses_count' => $this->usesCount,
            'max_uses' => $this->maxUses,
        ];
    }
}

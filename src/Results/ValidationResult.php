<?php

declare(strict_types=1);

namespace LicensesOS\Results;

class ValidationResult
{
    public readonly bool $valid;
    public readonly string $status;
    public readonly ?string $reasonCode;
    public readonly ?string $expiresAt;
    public readonly ?string $updatesUntil;
    public readonly array $entitlements;
    public readonly ?array $activation;
    public readonly ?string $offlineToken;
    public readonly string $requestId;

    public function __construct(array $data)
    {
        $this->valid = $data['valid'] ?? false;
        $this->status = $data['status'] ?? 'unknown';
        $this->reasonCode = $data['reason_code'] ?? null;
        $this->expiresAt = $data['expires_at'] ?? null;
        $this->updatesUntil = $data['updates_until'] ?? null;
        $this->entitlements = $data['entitlements'] ?? [];
        $this->activation = $data['activation'] ?? null;
        $this->offlineToken = $data['offline_token'] ?? null;
        $this->requestId = $data['request_id'] ?? '';
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function hasEntitlement(string $key): bool
    {
        return isset($this->entitlements[$key]);
    }

    public function getEntitlement(string $key, mixed $default = null): mixed
    {
        return $this->entitlements[$key] ?? $default;
    }

    public function getRemainingActivations(): ?int
    {
        return $this->activation['remaining'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'expires_at' => $this->expiresAt,
            'updates_until' => $this->updatesUntil,
            'entitlements' => $this->entitlements,
            'activation' => $this->activation,
            'offline_token' => $this->offlineToken,
            'request_id' => $this->requestId,
        ];
    }
}

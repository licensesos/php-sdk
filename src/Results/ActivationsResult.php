<?php

declare(strict_types=1);

namespace LicenseOS\Results;

class ActivationsResult
{
    public readonly string $licenseKey;
    public readonly array $activations;
    public readonly int $total;
    public readonly ?int $limit;
    public readonly ?int $remaining;
    public readonly string $requestId;

    public function __construct(array $data)
    {
        $this->licenseKey = $data['license_key'] ?? '';
        $this->activations = $data['activations'] ?? [];
        $this->total = $data['total'] ?? 0;
        $this->limit = $data['limit'] ?? null;
        $this->remaining = $data['remaining'] ?? null;
        $this->requestId = $data['request_id'] ?? '';
    }

    public function getActiveActivations(): array
    {
        return array_filter($this->activations, fn($a) => ($a['status'] ?? '') === 'active');
    }

    public function getActiveCount(): int
    {
        return count($this->getActiveActivations());
    }

    public function hasActivation(string $identifier): bool
    {
        foreach ($this->activations as $activation) {
            if (($activation['identifier'] ?? '') === $identifier) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'license_key' => $this->licenseKey,
            'activations' => $this->activations,
            'total' => $this->total,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'request_id' => $this->requestId,
        ];
    }
}

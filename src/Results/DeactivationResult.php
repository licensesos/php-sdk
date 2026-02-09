<?php

declare(strict_types=1);

namespace LicensesOS\Results;

class DeactivationResult
{
    public readonly bool $deactivated;
    public readonly ?int $remaining;
    public readonly ?array $error;
    public readonly string $requestId;

    public function __construct(array $data)
    {
        $this->deactivated = $data['deactivated'] ?? false;
        $this->remaining = $data['remaining'] ?? null;
        $this->error = $data['error'] ?? null;
        $this->requestId = $data['request_id'] ?? '';
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated;
    }

    public function getRemainingActivations(): ?int
    {
        return $this->remaining;
    }

    public function getErrorCode(): ?string
    {
        return $this->error['code'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'deactivated' => $this->deactivated,
            'remaining' => $this->remaining,
            'error' => $this->error,
            'request_id' => $this->requestId,
        ];
    }
}

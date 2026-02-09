<?php

declare(strict_types=1);

namespace LicensesOS\Results;

class ActivationResult
{
    public readonly bool $activated;
    public readonly ?array $license;
    public readonly ?array $activation;
    public readonly ?array $error;
    public readonly string $requestId;

    public function __construct(array $data)
    {
        $this->activated = $data['activated'] ?? false;
        $this->license = $data['license'] ?? null;
        $this->activation = $data['activation'] ?? null;
        $this->error = $data['error'] ?? null;
        $this->requestId = $data['request_id'] ?? '';
    }

    public function isActivated(): bool
    {
        return $this->activated;
    }

    public function getErrorCode(): ?string
    {
        return $this->error['code'] ?? null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error['message'] ?? null;
    }

    public function getRemainingActivations(): ?int
    {
        return $this->activation['remaining'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'activated' => $this->activated,
            'license' => $this->license,
            'activation' => $this->activation,
            'error' => $this->error,
            'request_id' => $this->requestId,
        ];
    }
}

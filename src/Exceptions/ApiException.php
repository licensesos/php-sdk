<?php

declare(strict_types=1);

namespace LicenseOS\Exceptions;

use Exception;

class ApiException extends Exception
{
    private string $errorCode;
    private int $statusCode;

    public function __construct(string $message, string $errorCode, int $statusCode)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

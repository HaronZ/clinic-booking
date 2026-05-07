<?php
declare(strict_types=1);

namespace Clinic\Exception;

use RuntimeException;

/**
 * Thrown when a booking conflicts with an existing one (HTTP 409).
 */
final class ConflictException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

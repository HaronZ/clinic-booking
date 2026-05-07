<?php
declare(strict_types=1);

namespace Clinic\Exception;

use RuntimeException;

/**
 * Thrown by RateLimiter when the caller has exceeded the per-key attempt
 * budget within the configured window. Mapped to HTTP 429 in the front
 * controller.
 */
final class RateLimitException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}

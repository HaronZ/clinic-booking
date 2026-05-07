<?php
declare(strict_types=1);

namespace Clinic\Exception;

use RuntimeException;

/**
 * Thrown for any client-input problem that should produce HTTP 422.
 * The string $code (e.g. "MISSING_FIELD") is exposed to the API consumer.
 *
 * Note: PHP's base Exception::$code is int. We override with a string
 * code that survives through the HTTP layer.
 */
final class ValidationException extends RuntimeException
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

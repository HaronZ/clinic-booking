<?php
declare(strict_types=1);

namespace Clinic\Exception;

use RuntimeException;

/**
 * Thrown when an authenticated user lacks the required role for an action (HTTP 403).
 *
 * Distinct from ValidationException (422) and from missing-auth which is also 422
 * via "UNAUTHORIZED" — this one specifically means "you're logged in, but not allowed".
 */
final class AuthorizationException extends RuntimeException
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

<?php
declare(strict_types=1);

namespace Clinic\Exception;

use RuntimeException;

/**
 * Thrown when a status transition is not in the allow-list.
 * Example: pending -> completed (must go through confirmed first).
 */
final class InvalidTransitionException extends RuntimeException
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Invalid status transition: %s -> %s',
            $from,
            $to,
        ));
    }

    public function getErrorCode(): string
    {
        return 'INVALID_TRANSITION';
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }
}

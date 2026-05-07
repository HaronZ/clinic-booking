<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Repository\ScheduleRepository;

/**
 * Provider schedules — full-week replace.
 *
 * The admin UI renders 7 day-of-week rows; the user fills in the days a
 * provider works and hits Save. We delete all existing rows for that
 * provider and bulk-insert the new set in one transaction.
 *
 * day_of_week 0=Sunday … 6=Saturday (matches CLAUDE.md and schema check).
 */
final class ScheduleManagementService
{
    private const TIME_RE = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly ScheduleRepository $schedules,
    ) {}

    /**
     * @return array<int,array{id:string,day_of_week:int,start_time:string,end_time:string}>
     */
    public function getForProvider(string $providerId): array
    {
        if ($this->providers->findById($providerId, includeInactive: true) === null) {
            throw new ValidationException('PROVIDER_NOT_FOUND', 'Provider does not exist');
        }
        return $this->schedules->findByProvider($providerId);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{id:string,day_of_week:int,start_time:string,end_time:string}>
     */
    public function replace(string $providerId, array $rows): array
    {
        if ($this->providers->findById($providerId, includeInactive: true) === null) {
            throw new ValidationException('PROVIDER_NOT_FOUND', 'Provider does not exist');
        }

        $cleaned = [];
        $seenDays = [];

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                throw new ValidationException(
                    'INVALID_SCHEDULE_ROW',
                    "Schedule row #{$i} must be an object",
                );
            }

            $dow = $row['day_of_week'] ?? null;
            if (!is_int($dow) && !(is_string($dow) && ctype_digit($dow))) {
                throw new ValidationException(
                    'INVALID_DAY',
                    "Schedule row #{$i}: day_of_week must be an integer 0-6",
                );
            }
            $dow = (int) $dow;
            if ($dow < 0 || $dow > 6) {
                throw new ValidationException(
                    'INVALID_DAY',
                    "Schedule row #{$i}: day_of_week must be 0 (Sunday) through 6 (Saturday)",
                );
            }
            if (isset($seenDays[$dow])) {
                throw new ValidationException(
                    'DUPLICATE_DAY',
                    "Schedule row #{$i}: day_of_week {$dow} appears more than once",
                );
            }
            $seenDays[$dow] = true;

            $start = $this->normaliseTime($row['start_time'] ?? null, $i, 'start_time');
            $end   = $this->normaliseTime($row['end_time']   ?? null, $i, 'end_time');

            if ($end <= $start) {
                throw new ValidationException(
                    'INVALID_RANGE',
                    "Schedule row #{$i}: end_time must be after start_time",
                );
            }

            $cleaned[] = [
                'day_of_week' => $dow,
                'start_time'  => $start,
                'end_time'    => $end,
            ];
        }

        $this->schedules->replaceForProvider($providerId, $cleaned);
        return $this->schedules->findByProvider($providerId);
    }

    private function normaliseTime(mixed $raw, int $i, string $field): string
    {
        if (!is_string($raw) || $raw === '') {
            throw new ValidationException(
                'INVALID_TIME',
                "Schedule row #{$i}: {$field} is required",
            );
        }
        if (preg_match(self::TIME_RE, $raw) !== 1) {
            throw new ValidationException(
                'INVALID_TIME',
                "Schedule row #{$i}: {$field} must look like HH:MM or HH:MM:SS",
            );
        }
        // Pad to HH:MM:SS for consistent storage.
        return strlen($raw) === 5 ? $raw . ':00' : $raw;
    }
}

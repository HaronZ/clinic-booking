<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\AvailabilityRepository;
use DateTimeImmutable;
use PDO;

/**
 * Slot generator. Provider schedule is the source of truth for working hours;
 * appointments table only filters out already-booked slots.
 *
 * Algorithm (per the plan):
 *   1. Validate date format & not-in-past
 *   2. Look up provider_schedules row for that day_of_week (404 if none)
 *   3. Look up duration_minutes from appointment_types
 *   4. slot_interval_minutes = duration_minutes (MVP rule)
 *   5. Generate slots from schedule.start_time, stepping by interval, while slot_end <= schedule.end_time
 *   6. Mark each slot available=false if any non-cancelled appointment overlaps it
 */
final class AvailabilityService
{
    private readonly AvailabilityRepository $availability;

    public function __construct(
        PDO $pdo,
        private readonly AppointmentRepository $appointments,
        ?AvailabilityRepository $availabilityRepo = null,
    ) {
        $this->availability = $availabilityRepo ?? new AvailabilityRepository($pdo);
    }

    /**
     * @return array{
     *   provider_id:string,
     *   date:string,
     *   slot_interval_minutes:int,
     *   slots:array<int,array{start_time:string,end_time:string,available:bool}>
     * }
     */
    public function getSlots(string $providerId, string $appointmentTypeId, string $date): array
    {
        // ---- a) date validation ----
        $dateDt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($dateDt === false || $dateDt->format('Y-m-d') !== $date) {
            throw new ValidationException(
                'INVALID_DATE',
                'date must be in format YYYY-MM-DD',
            );
        }
        $today = (new DateTimeImmutable('today'));
        if ($dateDt < $today) {
            throw new ValidationException(
                'INVALID_DATE',
                'date cannot be in the past',
            );
        }

        // ---- b) provider must exist & be active ----
        $provider = $this->availability->findActiveProvider($providerId);
        if ($provider === null) {
            throw new ValidationException(
                'PROVIDER_NOT_FOUND',
                'Provider does not exist or is inactive',
            );
        }

        // ---- c) appointment type must exist & be active ----
        $type = $this->availability->findActiveType($appointmentTypeId);
        if ($type === null) {
            throw new ValidationException(
                'TYPE_NOT_FOUND',
                'Appointment type does not exist or is inactive',
            );
        }

        // ---- d) provider schedule for that day_of_week ----
        $dayOfWeek = (int) $dateDt->format('w');
        $schedule  = $this->availability->findSchedule($providerId, $dayOfWeek);
        if ($schedule === null) {
            throw new ValidationException(
                'NO_SCHEDULE',
                'Provider has no working schedule on that day',
            );
        }

        // ---- e) generate slot windows from schedule ----
        $duration = $type['duration_minutes'];
        $interval = $duration; // MVP: slot_interval_minutes = duration_minutes

        $slotStart = new DateTimeImmutable($date . ' ' . $schedule['start_time']);
        $scheduleEnd = new DateTimeImmutable($date . ' ' . $schedule['end_time']);

        $slots = [];
        while (true) {
            $slotEnd = $slotStart->modify(sprintf('+%d minutes', $duration));
            if ($slotEnd > $scheduleEnd) {
                break;
            }
            $slots[] = [
                'start_time' => $slotStart->format('H:i'),
                'end_time'   => $slotEnd->format('H:i'),
                'available'  => true,
                // Internal: keep DateTime objects until we filter, then drop them.
                '_start_dt'  => $slotStart,
                '_end_dt'    => $slotEnd,
            ];
            $slotStart = $slotStart->modify(sprintf('+%d minutes', $interval));
        }

        // ---- f) overlay booked appointments ----
        $bookings = $this->appointments->findBookingsForDate($providerId, $date);

        foreach ($slots as &$slot) {
            foreach ($bookings as $booking) {
                $bookingStart = new DateTimeImmutable((string) $booking['start_time']);
                $bookingEnd   = new DateTimeImmutable((string) $booking['end_time']);
                // Standard overlap predicate.
                if ($bookingStart < $slot['_end_dt'] && $bookingEnd > $slot['_start_dt']) {
                    $slot['available'] = false;
                    break;
                }
            }
            unset($slot['_start_dt'], $slot['_end_dt']);
        }
        unset($slot);

        return [
            'provider_id'           => $providerId,
            'date'                  => $date,
            'slot_interval_minutes' => $interval,
            'slots'                 => $slots,
        ];
    }
}

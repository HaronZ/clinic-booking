<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

/**
 * Reads against provider_schedules + appointment_types for the slot generator.
 * Booked-slot lookup lives in AppointmentRepository::findBookingsForDate.
 */
final class AvailabilityRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Returns the schedule row for (provider, day_of_week) or null.
     *
     * @return array{day_of_week:int,start_time:string,end_time:string}|null
     */
    public function findSchedule(string $providerId, int $dayOfWeek): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT day_of_week, start_time, end_time
               FROM provider_schedules
              WHERE provider_id = :provider_id
                AND day_of_week = :day_of_week'
        );
        $stmt->execute([
            'provider_id' => $providerId,
            'day_of_week' => $dayOfWeek,
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'day_of_week' => (int) $row['day_of_week'],
            'start_time'  => (string) $row['start_time'],
            'end_time'    => (string) $row['end_time'],
        ];
    }

    /**
     * @return array{id:string,name:string,duration_minutes:int}|null
     */
    public function findActiveType(string $typeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, duration_minutes
               FROM appointment_types
              WHERE id = :id
                AND is_active = 1'
        );
        $stmt->execute(['id' => $typeId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id'               => (string) $row['id'],
            'name'             => (string) $row['name'],
            'duration_minutes' => (int) $row['duration_minutes'],
        ];
    }

    /**
     * Active provider lookup (mirrors ProviderRepository::findById; kept here
     * so AvailabilityService doesn't need both repos).
     *
     * @return array{id:string,name:string}|null
     */
    public function findActiveProvider(string $providerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name
               FROM providers
              WHERE id = :id
                AND is_active = 1'
        );
        $stmt->execute(['id' => $providerId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id'   => (string) $row['id'],
            'name' => (string) $row['name'],
        ];
    }
}

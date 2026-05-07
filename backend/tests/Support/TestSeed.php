<?php
declare(strict_types=1);

namespace Clinic\Tests\Support;

use DateTimeImmutable;
use PDO;
use Ramsey\Uuid\Uuid;

/**
 * Seed/teardown helpers for booking + availability tests.
 *
 * Each test calls TestSeed::reset($pdo) in setUp() and seeds whatever
 * fixtures it needs. Because schedules use day_of_week (not specific dates),
 * tests that need a known weekday compute it dynamically via TestSeed::nextDate.
 */
final class TestSeed
{
    /** Truncate every domain table in dependency-safe order. */
    public static function reset(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE appointments');
        $pdo->exec('TRUNCATE TABLE provider_schedules');
        $pdo->exec('TRUNCATE TABLE appointment_types');
        $pdo->exec('TRUNCATE TABLE providers');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public static function provider(
        PDO $pdo,
        string $name = 'Dr. Test',
        string $specialty = 'General Medicine',
        bool $active = true,
        ?string $slug = null,
    ): string {
        $id   = Uuid::uuid4()->toString();
        $slug = $slug ?? 'dr-test-' . substr($id, 0, 8);
        $stmt = $pdo->prepare(
            'INSERT INTO providers (id, name, specialty, slug, is_active)
                  VALUES (:id, :name, :specialty, :slug, :active)'
        );
        $stmt->execute([
            'id'        => $id,
            'name'      => $name,
            'specialty' => $specialty,
            'slug'      => $slug,
            'active'    => $active ? 1 : 0,
        ]);
        return $id;
    }

    public static function appointmentType(
        PDO $pdo,
        string $name = 'General Consultation',
        int $duration = 30,
        bool $active = true,
        ?string $slug = null,
    ): string {
        $id   = Uuid::uuid4()->toString();
        $slug = $slug ?? 'appt-' . substr($id, 0, 8);
        $stmt = $pdo->prepare(
            'INSERT INTO appointment_types (id, name, slug, duration_minutes, is_active)
                  VALUES (:id, :name, :slug, :duration, :active)'
        );
        $stmt->execute([
            'id'       => $id,
            'name'     => $name,
            'slug'     => $slug,
            'duration' => $duration,
            'active'   => $active ? 1 : 0,
        ]);
        return $id;
    }

    /**
     * Insert a working schedule row for (provider, day_of_week).
     * Default: 09:00–17:00.
     */
    public static function schedule(
        PDO $pdo,
        string $providerId,
        int $dayOfWeek,
        string $startTime = '09:00:00',
        string $endTime   = '17:00:00',
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time)
                  VALUES (:id, :provider_id, :day, :start, :end)'
        );
        $stmt->execute([
            'id'          => Uuid::uuid4()->toString(),
            'provider_id' => $providerId,
            'day'         => $dayOfWeek,
            'start'       => $startTime,
            'end'         => $endTime,
        ]);
    }

    /**
     * Insert an existing appointment directly (bypasses BookingService).
     * Used by overlap / availability tests.
     */
    public static function appointment(
        PDO $pdo,
        string $providerId,
        string $appointmentTypeId,
        string $startTime,
        string $endTime,
        string $status = 'pending',
        string $patientName = 'Existing Patient',
        string $patientPhone = '+1-555-0000',
    ): string {
        $id = Uuid::uuid4()->toString();
        $stmt = $pdo->prepare(
            'INSERT INTO appointments
               (id, provider_id, appointment_type_id,
                patient_name, patient_phone, start_time, end_time, status)
             VALUES
               (:id, :pid, :tid,
                :name, :phone, :start, :end, :status)'
        );
        $stmt->execute([
            'id'     => $id,
            'pid'    => $providerId,
            'tid'    => $appointmentTypeId,
            'name'   => $patientName,
            'phone'  => $patientPhone,
            'start'  => $startTime,
            'end'    => $endTime,
            'status' => $status,
        ]);
        return $id;
    }

    /**
     * Returns the next date (>= today) matching $dayOfWeek (0=Sun..6=Sat).
     * Used so tests don't depend on the calendar.
     */
    public static function nextDate(int $dayOfWeek): DateTimeImmutable
    {
        $cursor = new DateTimeImmutable('today');
        for ($i = 0; $i < 7; $i++) {
            if ((int) $cursor->format('w') === $dayOfWeek) {
                return $cursor;
            }
            $cursor = $cursor->modify('+1 day');
        }
        // Unreachable: 7-day window must contain every day_of_week.
        throw new \LogicException('Could not find requested day_of_week');
    }
}

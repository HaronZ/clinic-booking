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
        $pdo->exec('TRUNCATE TABLE staff_users');
        // auth_attempts may not exist on older test DBs that haven't been
        // migrated through 007 yet — guard so reset() still works.
        try {
            $pdo->exec('TRUNCATE TABLE auth_attempts');
        } catch (\PDOException) {
            // table missing; tests that don't touch RateLimiter don't care
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insert a staff user directly — useful for auth/authorization tests.
     * Password is stored as bcrypt hash of $plainPassword.
     */
    public static function staffUser(
        PDO $pdo,
        string $username = 'testadmin',
        string $plainPassword = 'password123',
        string $role = 'admin',
        ?string $providerId = null,
        int $mustChangePassword = 0,
    ): string {
        $id   = Uuid::uuid4()->toString();
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare(
            'INSERT INTO staff_users
                   (id, username, name, password, provider_id, role,
                    is_active, must_change_password)
                  VALUES (:id, :u, :n, :p, :pid, :r, 1, :mcp)'
        );
        $stmt->execute([
            'id'  => $id,
            'u'   => $username,
            'n'   => ucfirst($role) . ' User',
            'p'   => $hash,
            'pid' => $providerId,
            'r'   => $role,
            'mcp' => $mustChangePassword,
        ]);
        return $id;
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

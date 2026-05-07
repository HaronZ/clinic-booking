<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ConflictException;
use Clinic\Exception\InvalidTransitionException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\ProviderRepository;
use Clinic\Service\BookingService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class BookingServiceTest extends TestCase
{
    private PDO $pdo;
    private BookingService $service;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo);

        $this->service = new BookingService(
            $this->pdo,
            new ProviderRepository($this->pdo),
            new AppointmentRepository($this->pdo),
        );
    }

    /**
     * Build a valid booking input pointing at the next occurrence of $dayOfWeek
     * (default Monday) at 09:00, with the seeded provider + 30-min type.
     *
     * @return array{0:array<string,mixed>,1:string,2:string} [input, providerId, typeId]
     */
    private function happyPathFixtures(int $dayOfWeek = 1, int $duration = 30): array
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo, duration: $duration);
        TestSeed::schedule($this->pdo, $providerId, $dayOfWeek);

        $date  = TestSeed::nextDate($dayOfWeek);
        $start = $date->format('Y-m-d') . 'T09:00:00';

        $input = [
            'provider_id'         => $providerId,
            'appointment_type_id' => $typeId,
            'start_time'          => $start,
            'patient_name'        => 'Maria Santos',
            'patient_phone'       => '+63-917-555-0100',
        ];

        return [$input, $providerId, $typeId];
    }

    // ---------------- a ----------------
    public function testHappyPathCreatesRowWithCorrectStatusAndEndTime(): void
    {
        [$input] = $this->happyPathFixtures();
        $expectedEnd = substr($input['start_time'], 0, 11) . '09:30:00';

        $result = $this->service->create($input);

        $this->assertSame('pending', $result['status']);
        $this->assertSame(
            str_replace('T', ' ', $input['start_time']),
            $result['start_time'],
        );
        $this->assertSame(
            str_replace('T', ' ', $expectedEnd),
            $result['end_time'],
        );

        // Row exists in DB.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appointments WHERE id = ?');
        $stmt->execute([$result['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        // PII MUST NOT be in the response payload.
        $this->assertArrayNotHasKey('patient_name', $result);
        $this->assertArrayNotHasKey('patient_email', $result);
    }

    // ---------------- b ----------------
    public function testMissingPatientNameThrowsValidation(): void
    {
        [$input] = $this->happyPathFixtures();
        unset($input['patient_name']);

        try {
            $this->service->create($input);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('MISSING_FIELD', $e->getErrorCode());
        }
    }

    // ---------------- c ----------------
    public function testMissingProviderIdThrowsValidation(): void
    {
        [$input] = $this->happyPathFixtures();
        unset($input['provider_id']);

        try {
            $this->service->create($input);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('MISSING_FIELD', $e->getErrorCode());
        }
    }

    // ---------------- d ----------------
    public function testInvalidStartTimeDatetimeThrowsValidation(): void
    {
        [$input] = $this->happyPathFixtures();
        $input['start_time'] = 'not-a-date';

        try {
            $this->service->create($input);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('INVALID_DATETIME', $e->getErrorCode());
        }
    }

    // ---------------- e ----------------
    public function testInactiveProviderThrowsValidation(): void
    {
        $providerId = TestSeed::provider($this->pdo, active: false);
        $typeId     = TestSeed::appointmentType($this->pdo);
        TestSeed::schedule($this->pdo, $providerId, 1);
        $date = TestSeed::nextDate(1);

        try {
            $this->service->create([
                'provider_id'         => $providerId,
                'appointment_type_id' => $typeId,
                'start_time'          => $date->format('Y-m-d') . 'T09:00:00',
                'patient_name'        => 'X',
                'patient_phone'       => '+1-555-0000',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('PROVIDER_NOT_FOUND', $e->getErrorCode());
        }
    }

    // ---------------- f ----------------
    public function testInactiveAppointmentTypeThrowsValidation(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo, active: false);
        TestSeed::schedule($this->pdo, $providerId, 1);
        $date = TestSeed::nextDate(1);

        try {
            $this->service->create([
                'provider_id'         => $providerId,
                'appointment_type_id' => $typeId,
                'start_time'          => $date->format('Y-m-d') . 'T09:00:00',
                'patient_name'        => 'X',
                'patient_phone'       => '+1-555-0000',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('TYPE_NOT_FOUND', $e->getErrorCode());
        }
    }

    // ---------------- g ----------------
    public function testOverlappingBookingThrowsConflict(): void
    {
        [$input, $providerId, $typeId] = $this->happyPathFixtures();
        $date  = substr($input['start_time'], 0, 10);

        // Existing booking 09:00–09:30
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:00:00',
            $date . ' 09:30:00',
        );

        // New booking 09:15–09:45 (partial overlap)
        $input['start_time'] = $date . 'T09:15:00';

        try {
            $this->service->create($input);
            $this->fail('Expected ConflictException');
        } catch (ConflictException $e) {
            $this->assertSame('TIME_SLOT_TAKEN', $e->getErrorCode());
        }

        // Only the original row should exist.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appointments WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ---------------- h ----------------
    public function testAdjacentBookingIsAllowed(): void
    {
        [$input, $providerId, $typeId] = $this->happyPathFixtures();
        $date  = substr($input['start_time'], 0, 10);

        // Existing 09:00–09:30, status=confirmed
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:00:00',
            $date . ' 09:30:00',
            'confirmed',
        );

        // New 09:30–10:00 — exactly adjacent, no overlap.
        $input['start_time'] = $date . 'T09:30:00';

        $result = $this->service->create($input);
        $this->assertSame('pending', $result['status']);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appointments WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    // ---------------- i ----------------
    public function testCancelledBookingInSameSlotDoesNotBlock(): void
    {
        [$input, $providerId, $typeId] = $this->happyPathFixtures();
        $date  = substr($input['start_time'], 0, 10);

        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:00:00',
            $date . ' 09:30:00',
            'cancelled',
        );

        $result = $this->service->create($input);
        $this->assertSame('pending', $result['status']);

        $stmt = $this->pdo->prepare(
            'SELECT status FROM appointments WHERE provider_id = ?'
        );
        $stmt->execute([$providerId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // assertEqualsCanonicalizing ignores order — we just care both rows exist.
        $this->assertEqualsCanonicalizing(['cancelled', 'pending'], $statuses);
    }

    // ---------------- j ----------------
    public function testEndTimeIgnoresClientDurationInput(): void
    {
        [$input] = $this->happyPathFixtures(duration: 30);
        $input['duration_minutes'] = 999; // attempt to override
        $input['end_time']         = $input['start_time']; // attempt to override
        $expectedEnd = substr($input['start_time'], 0, 11) . '09:30:00';

        $result = $this->service->create($input);
        $this->assertSame(
            str_replace('T', ' ', $expectedEnd),
            $result['end_time'],
            'end_time must be derived from appointment_type, not client input',
        );

        // Verify against DB row directly.
        $stmt = $this->pdo->prepare('SELECT end_time FROM appointments WHERE id = ?');
        $stmt->execute([$result['id']]);
        $this->assertSame(
            str_replace('T', ' ', $expectedEnd),
            (string) $stmt->fetchColumn(),
        );
    }

    // ---------------- k ----------------
    public function testBookingOutsideScheduleHoursThrowsValidation(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo);
        TestSeed::schedule($this->pdo, $providerId, 1, '09:00:00', '17:00:00');
        $date = TestSeed::nextDate(1);

        try {
            $this->service->create([
                'provider_id'         => $providerId,
                'appointment_type_id' => $typeId,
                'start_time'          => $date->format('Y-m-d') . 'T18:00:00',
                'patient_name'        => 'X',
                'patient_phone'       => '+1-555-0000',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('OUTSIDE_SCHEDULE', $e->getErrorCode());
        }
    }

    // ---------------- bonus: status transition machine ----------------
    public function testInvalidStatusTransitionThrows(): void
    {
        $this->expectException(InvalidTransitionException::class);
        $this->service->transition('completed', 'pending');
    }

    public function testValidStatusTransitionsAllowed(): void
    {
        // No exception => pass.
        $this->service->transition('pending', 'confirmed');
        $this->service->transition('pending', 'cancelled');
        $this->service->transition('confirmed', 'completed');
        $this->service->transition('confirmed', 'cancelled');
        $this->assertTrue(true);
    }
}

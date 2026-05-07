<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentRepository;
use Clinic\Service\AvailabilityService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    private PDO $pdo;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo);

        $this->service = new AvailabilityService(
            $this->pdo,
            new AppointmentRepository($this->pdo),
        );
    }

    /**
     * Common fixture: provider with Monday 09:00-12:00 schedule, 30-min type.
     *
     * @return array{0:string,1:string,2:string} [providerId, typeId, dateString]
     */
    private function fixtures(int $duration = 30, string $start = '09:00:00', string $end = '12:00:00'): array
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo, duration: $duration);
        $dayOfWeek  = 1; // Monday
        TestSeed::schedule($this->pdo, $providerId, $dayOfWeek, $start, $end);
        $date = TestSeed::nextDate($dayOfWeek)->format('Y-m-d');
        return [$providerId, $typeId, $date];
    }

    // ---------------- a ----------------
    public function testSixSlotsMondayAllAvailable(): void
    {
        [$providerId, $typeId, $date] = $this->fixtures();

        $result = $this->service->getSlots($providerId, $typeId, $date);

        $this->assertSame($date, $result['date']);
        $this->assertSame(30, $result['slot_interval_minutes']);
        $this->assertCount(6, $result['slots']);

        $expectedStarts = ['09:00','09:30','10:00','10:30','11:00','11:30'];
        foreach ($result['slots'] as $i => $slot) {
            $this->assertSame($expectedStarts[$i], $slot['start_time']);
            $this->assertTrue($slot['available'], "slot {$slot['start_time']} should be available");
        }
    }

    // ---------------- b ----------------
    public function testOneBookingMarksSingleSlotUnavailable(): void
    {
        [$providerId, $typeId, $date] = $this->fixtures();
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:00:00',
            $date . ' 09:30:00',
            'confirmed',
        );

        $slots = $this->service->getSlots($providerId, $typeId, $date)['slots'];

        $this->assertFalse($slots[0]['available'], '09:00 must be unavailable');
        for ($i = 1; $i < 6; $i++) {
            $this->assertTrue($slots[$i]['available'], "{$slots[$i]['start_time']} must remain available");
        }
    }

    // ---------------- c ----------------
    public function testPartialOverlapMarksTwoSlotsUnavailable(): void
    {
        [$providerId, $typeId, $date] = $this->fixtures();
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:15:00',
            $date . ' 09:45:00',
        );

        $slots = $this->service->getSlots($providerId, $typeId, $date)['slots'];

        // 09:00–09:30 overlaps with 09:15–09:45 → unavailable
        $this->assertFalse($slots[0]['available'], 'slot 09:00 must be unavailable');
        // 09:30–10:00 overlaps with 09:15–09:45 → unavailable
        $this->assertFalse($slots[1]['available'], 'slot 09:30 must be unavailable');
        // 10:00 onwards should be free.
        for ($i = 2; $i < 6; $i++) {
            $this->assertTrue($slots[$i]['available']);
        }
    }

    // ---------------- d ----------------
    public function testNoScheduleForDayReturnsError(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo);
        TestSeed::schedule($this->pdo, $providerId, 1); // Monday only
        $date = TestSeed::nextDate(0)->format('Y-m-d'); // Sunday

        try {
            $this->service->getSlots($providerId, $typeId, $date);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('NO_SCHEDULE', $e->getErrorCode());
        }
    }

    // ---------------- e ----------------
    public function testPastDateReturnsError(): void
    {
        [$providerId, $typeId] = $this->fixtures();
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        try {
            $this->service->getSlots($providerId, $typeId, $yesterday);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('INVALID_DATE', $e->getErrorCode());
        }
    }

    // ---------------- f ----------------
    public function testCancelledBookingDoesNotBlockSlot(): void
    {
        [$providerId, $typeId, $date] = $this->fixtures();
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            $date . ' 09:00:00',
            $date . ' 09:30:00',
            'cancelled',
        );

        $slots = $this->service->getSlots($providerId, $typeId, $date)['slots'];
        $this->assertTrue($slots[0]['available'], 'cancelled bookings must NOT block availability');
    }

    // ---------------- g ----------------
    public function testInactiveProviderReturnsError(): void
    {
        $providerId = TestSeed::provider($this->pdo, active: false);
        $typeId     = TestSeed::appointmentType($this->pdo);
        TestSeed::schedule($this->pdo, $providerId, 1);
        $date = TestSeed::nextDate(1)->format('Y-m-d');

        try {
            $this->service->getSlots($providerId, $typeId, $date);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('PROVIDER_NOT_FOUND', $e->getErrorCode());
        }
    }

    // ---------------- h ----------------
    public function testInactiveAppointmentTypeReturnsError(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $typeId     = TestSeed::appointmentType($this->pdo, active: false);
        TestSeed::schedule($this->pdo, $providerId, 1);
        $date = TestSeed::nextDate(1)->format('Y-m-d');

        try {
            $this->service->getSlots($providerId, $typeId, $date);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('TYPE_NOT_FOUND', $e->getErrorCode());
        }
    }
}

<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Repository\ScheduleRepository;
use Clinic\Service\ScheduleManagementService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class ScheduleManagementServiceTest extends TestCase
{
    private PDO $pdo;
    private ScheduleManagementService $service;
    private string $providerId;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo);

        $this->service    = new ScheduleManagementService(
            new ProviderRepository($this->pdo),
            new ScheduleRepository($this->pdo),
        );
        $this->providerId = TestSeed::provider($this->pdo);
    }

    /** Build a single schedule-row array. */
    private function row(int $dow, string $start = '09:00', string $end = '17:00'): array
    {
        return ['day_of_week' => $dow, 'start_time' => $start, 'end_time' => $end];
    }

    // ── replace — happy path ──────────────────────────────────────────────────

    public function testReplaceStoresTwoRowsAndNormalisesTime(): void
    {
        $rows = $this->service->replace($this->providerId, [
            $this->row(1, '08:00', '16:00'),
            $this->row(3, '10:00', '18:00'),
        ]);

        $this->assertCount(2, $rows);

        // Times padded from HH:MM → HH:MM:SS.
        $this->assertSame('08:00:00', $rows[0]['start_time']);
        $this->assertSame('16:00:00', $rows[0]['end_time']);
        $this->assertSame('10:00:00', $rows[1]['start_time']);
    }

    public function testReplaceAlreadyPaddedTimesStoredAsIs(): void
    {
        $rows = $this->service->replace($this->providerId, [
            $this->row(2, '09:30:00', '17:30:00'),
        ]);

        $this->assertSame('09:30:00', $rows[0]['start_time']);
        $this->assertSame('17:30:00', $rows[0]['end_time']);
    }

    public function testReplaceFullWeekSevenRows(): void
    {
        $input = array_map(fn($d) => $this->row($d), range(0, 6));
        $rows  = $this->service->replace($this->providerId, $input);

        $this->assertCount(7, $rows);
    }

    public function testReplaceWithEmptyArrayClearsSchedule(): void
    {
        $this->service->replace($this->providerId, [$this->row(1)]);
        $rows = $this->service->replace($this->providerId, []);

        $this->assertCount(0, $rows);
    }

    public function testReplaceOverwritesPreviousRows(): void
    {
        // First save: Mon+Tue.
        $this->service->replace($this->providerId, [
            $this->row(1),
            $this->row(2),
        ]);

        // Second save: only Wed — the previous two must be gone.
        $rows = $this->service->replace($this->providerId, [
            $this->row(3),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]['day_of_week']);
    }

    // ── getForProvider ────────────────────────────────────────────────────────

    public function testGetForProviderReturnsExistingRows(): void
    {
        $this->service->replace($this->providerId, [$this->row(2, '09:00', '12:00')]);
        $rows = $this->service->getForProvider($this->providerId);

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows[0]['day_of_week']);
    }

    public function testGetForProviderReturnsEmptyForNoSchedule(): void
    {
        $rows = $this->service->getForProvider($this->providerId);

        $this->assertSame([], $rows);
    }

    public function testGetForProviderThrowsForUnknownProvider(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Provider does not exist');

        $this->service->getForProvider('00000000-0000-0000-0000-000000000000');
    }

    // ── replace — validation failures ────────────────────────────────────────

    public function testReplaceRejectsEndBeforeStart(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('end_time must be after start_time');

        $this->service->replace($this->providerId, [
            $this->row(1, '17:00', '09:00'),
        ]);
    }

    public function testReplaceRejectsEndEqualStart(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('end_time must be after start_time');

        $this->service->replace($this->providerId, [
            $this->row(1, '09:00', '09:00'),
        ]);
    }

    public function testReplaceRejectsDayOfWeekTooHigh(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('day_of_week must be 0');

        $this->service->replace($this->providerId, [
            $this->row(7), // 7 is out of range (valid: 0–6)
        ]);
    }

    public function testReplaceRejectsDayOfWeekNegative(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->replace($this->providerId, [
            $this->row(-1),
        ]);
    }

    public function testReplaceRejectsDuplicateDayInSameBatch(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('day_of_week 1 appears more than once');

        $this->service->replace($this->providerId, [
            $this->row(1, '09:00', '12:00'),
            $this->row(1, '14:00', '18:00'),
        ]);
    }

    public function testReplaceRejectsInvalidTimeFormat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('start_time');

        $this->service->replace($this->providerId, [
            ['day_of_week' => 1, 'start_time' => 'not-a-time', 'end_time' => '17:00'],
        ]);
    }

    public function testReplaceRejectsUnknownProvider(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Provider does not exist');

        $this->service->replace('00000000-0000-0000-0000-000000000000', [$this->row(1)]);
    }
}

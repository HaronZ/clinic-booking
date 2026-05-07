<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentTypeRepository;
use Clinic\Service\AppointmentTypeService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class AppointmentTypeServiceTest extends TestCase
{
    private PDO $pdo;
    private AppointmentTypeService $service;
    private AppointmentTypeRepository $repo;

    protected function setUp(): void
    {
        $this->pdo     = Connection::get();
        TestSeed::reset($this->pdo);

        $this->repo    = new AppointmentTypeRepository($this->pdo);
        $this->service = new AppointmentTypeService($this->repo);
    }

    // ── create ───────────────────────────────────────────────────────────────

    public function testCreateWithMinimalFieldsAutoGeneratesSlug(): void
    {
        $row = $this->service->create([
            'name'             => 'General Consultation',
            'duration_minutes' => 30,
        ]);

        $this->assertSame('General Consultation', $row['name']);
        $this->assertSame(30, (int) $row['duration_minutes']);
        $this->assertSame('general-consultation', $row['slug']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertNotEmpty($row['id']);
    }

    public function testCreateWithExplicitSlugNormalisesIt(): void
    {
        $row = $this->service->create([
            'name'             => 'Blood Work',
            'duration_minutes' => 20,
            'slug'             => 'My Custom Slug',
        ]);

        $this->assertSame('my-custom-slug', $row['slug']);
    }

    public function testCreateAppendsSuffixOnSlugCollision(): void
    {
        $first  = $this->service->create(['name' => 'Follow Up', 'duration_minutes' => 15]);
        $second = $this->service->create(['name' => 'Follow Up', 'duration_minutes' => 30]);
        $third  = $this->service->create(['name' => 'Follow Up', 'duration_minutes' => 60]);

        $this->assertSame('follow-up',   $first['slug']);
        $this->assertSame('follow-up-2', $second['slug']);
        $this->assertSame('follow-up-3', $third['slug']);
    }

    public function testCreateRejectsMissingName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'name' is required");

        $this->service->create(['duration_minutes' => 30]);
    }

    public function testCreateRejectsMissingDuration(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('duration_minutes');

        $this->service->create(['name' => 'Checkup']);
    }

    public function testCreateRejectsDurationBelowMinimum(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('duration_minutes must be between');

        $this->service->create(['name' => 'Blink', 'duration_minutes' => 0]);
    }

    public function testCreateRejectsDurationAboveMaximum(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('duration_minutes must be between');

        $this->service->create(['name' => 'Marathon', 'duration_minutes' => 481]);
    }

    public function testCreateAcceptsBoundaryDurations(): void
    {
        $min = $this->service->create(['name' => 'Min', 'duration_minutes' => 1]);
        $max = $this->service->create(['name' => 'Max', 'duration_minutes' => 480]);

        $this->assertSame(1,   (int) $min['duration_minutes']);
        $this->assertSame(480, (int) $max['duration_minutes']);
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function testUpdateChangesDurationOnly(): void
    {
        $row     = $this->service->create(['name' => 'Check-up', 'duration_minutes' => 20]);
        $updated = $this->service->update($row['id'], ['duration_minutes' => 45]);

        $this->assertSame(45, (int) $updated['duration_minutes']);
        $this->assertSame('Check-up', $updated['name']); // name preserved
        $this->assertSame($row['slug'], $updated['slug']); // slug unchanged
    }

    public function testUpdateAllowsKeepingOwnSlug(): void
    {
        $row     = $this->service->create(['name' => 'Labs', 'duration_minutes' => 30]);
        $updated = $this->service->update($row['id'], ['slug' => $row['slug']]);

        $this->assertSame($row['slug'], $updated['slug']);
    }

    public function testUpdateMissingTypeThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Appointment type does not exist');

        $this->service->update('00000000-0000-0000-0000-000000000000', ['name' => 'X']);
    }

    public function testUpdateRejectsBadDuration(): void
    {
        $row = $this->service->create(['name' => 'Visit', 'duration_minutes' => 30]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('duration_minutes must be between');

        $this->service->update($row['id'], ['duration_minutes' => 999]);
    }

    // ── deactivate / reactivate ──────────────────────────────────────────────

    public function testDeactivateSetsIsActiveZero(): void
    {
        $row = $this->service->create(['name' => 'Short Visit', 'duration_minutes' => 15]);

        $this->service->deactivate($row['id']);

        // Active-only lookup returns null.
        $this->assertNull($this->repo->findById($row['id']));
        // With includeInactive it's still there, just inactive.
        $still = $this->repo->findById($row['id'], includeInactive: true);
        $this->assertNotNull($still);
        $this->assertSame(0, (int) $still['is_active']);
    }

    public function testReactivateBringsBack(): void
    {
        $row = $this->service->create(['name' => 'Long Visit', 'duration_minutes' => 60]);
        $this->service->deactivate($row['id']);
        $this->service->reactivate($row['id']);

        $restored = $this->repo->findById($row['id']);
        $this->assertNotNull($restored);
        $this->assertSame(1, (int) $restored['is_active']);
    }

    public function testDeactivateMissingTypeThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Appointment type does not exist');

        $this->service->deactivate('00000000-0000-0000-0000-000000000000');
    }
}

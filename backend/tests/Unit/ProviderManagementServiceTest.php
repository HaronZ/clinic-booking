<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Service\ProviderManagementService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class ProviderManagementServiceTest extends TestCase
{
    private PDO $pdo;
    private ProviderManagementService $service;
    private ProviderRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo);

        $this->repo    = new ProviderRepository($this->pdo);
        $this->service = new ProviderManagementService($this->repo);
    }

    // ── create ───────────────────────────────────────────────────────────────

    public function testCreateWithMinimalFieldsAutoGeneratesSlug(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Juan Santos',
            'specialty' => 'Cardiology',
        ]);

        $this->assertSame('Dr. Juan Santos', $row['name']);
        $this->assertSame('Cardiology', $row['specialty']);
        $this->assertSame('dr-juan-santos', $row['slug']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertNotEmpty($row['id']);
    }

    public function testCreateWithExplicitSlugStillNormalises(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Test',
            'specialty' => 'General',
            'slug'      => 'My Custom Slug',
        ]);

        // The explicit slug is run through Slug::fromName() to normalise it.
        $this->assertSame('my-custom-slug', $row['slug']);
    }

    public function testCreateAppendsSuffixOnSlugCollision(): void
    {
        $first  = $this->service->create([
            'name'      => 'Dr. Smith',
            'specialty' => 'Cardiology',
        ]);
        $second = $this->service->create([
            'name'      => 'Dr. Smith',
            'specialty' => 'Pediatrics',
        ]);
        $third  = $this->service->create([
            'name'      => 'Dr. Smith',
            'specialty' => 'Orthopedics',
        ]);

        $this->assertSame('dr-smith',   $first['slug']);
        $this->assertSame('dr-smith-2', $second['slug']);
        $this->assertSame('dr-smith-3', $third['slug']);
    }

    public function testCreateRejectsMissingName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'name' is required");

        $this->service->create(['specialty' => 'Cardiology']);
    }

    public function testCreateRejectsBlankSpecialty(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create(['name' => 'Dr. X', 'specialty' => '   ']);
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function testUpdateChangesNameOnly(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Old',
            'specialty' => 'General',
        ]);

        $updated = $this->service->update($row['id'], ['name' => 'Dr. New Name']);

        $this->assertSame('Dr. New Name', $updated['name']);
        // Specialty preserved.
        $this->assertSame('General', $updated['specialty']);
        // Slug stays the same on a name-only edit (we only touch slug if caller asked).
        $this->assertSame($row['slug'], $updated['slug']);
    }

    public function testUpdateAllowsKeepingOwnSlug(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Same',
            'specialty' => 'General',
        ]);

        // Updating with the existing slug should not collide with itself.
        $updated = $this->service->update($row['id'], ['slug' => $row['slug']]);
        $this->assertSame($row['slug'], $updated['slug']);
    }

    public function testUpdateMissingProviderThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Provider does not exist');
        $this->service->update('00000000-0000-0000-0000-000000000000', ['name' => 'X']);
    }

    // ── deactivate / reactivate ──────────────────────────────────────────────

    public function testDeactivateSetsIsActiveZero(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Bye',
            'specialty' => 'General',
        ]);

        $this->service->deactivate($row['id']);

        // Default findById hides inactive — should now return null.
        $this->assertNull($this->repo->findById($row['id']));
        // With includeInactive flag, still findable.
        $still = $this->repo->findById($row['id'], includeInactive: true);
        $this->assertNotNull($still);
        $this->assertSame(0, (int) $still['is_active']);
    }

    public function testReactivateBringsBack(): void
    {
        $row = $this->service->create([
            'name'      => 'Dr. Back',
            'specialty' => 'General',
        ]);
        $this->service->deactivate($row['id']);
        $this->service->reactivate($row['id']);

        $row2 = $this->repo->findById($row['id']);
        $this->assertNotNull($row2);
        $this->assertSame(1, (int) $row2['is_active']);
    }

    public function testDeactivatePreservesHistoricalAppointments(): void
    {
        // Soft-delete must not break FK from appointments.
        $providerId = $this->service->create([
            'name'      => 'Dr. Closing',
            'specialty' => 'General',
        ])['id'];
        $typeId = TestSeed::appointmentType($this->pdo);
        TestSeed::appointment(
            $this->pdo,
            $providerId,
            $typeId,
            '2030-01-01 10:00:00',
            '2030-01-01 10:30:00',
        );

        $this->service->deactivate($providerId);

        // Appointment row must still exist and still reference the (inactive) provider.
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM appointments WHERE provider_id = ?'
        );
        $stmt->execute([$providerId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}

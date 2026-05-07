<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\ConflictException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Repository\StaffRepository;
use Clinic\Service\StaffManagementService;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

final class StaffManagementServiceTest extends TestCase
{
    private PDO $pdo;
    private StaffManagementService $service;
    private StaffRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo); // also truncates staff_users now

        $this->repo    = new StaffRepository($this->pdo);
        $this->service = new StaffManagementService(
            $this->repo,
            new ProviderRepository($this->pdo),
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(string $username = 'admin', string $pass = 'password123'): array
    {
        return $this->service->create([
            'username' => $username,
            'name'     => 'Admin User',
            'password' => $pass,
            'role'     => 'admin',
        ]);
    }

    // ── create ───────────────────────────────────────────────────────────────

    public function testCreateStoresHashedPassword(): void
    {
        $row = $this->makeAdmin();

        $this->assertNotEmpty($row['id']);
        $this->assertSame('admin', $row['username']);
        $this->assertSame('admin', $row['role']);
        // Password hash must NOT appear in the returned row.
        $this->assertArrayNotHasKey('password', $row);

        // But the hash must be correct in the DB.
        $dbRow = $this->repo->findById($row['id']);
        $this->assertTrue(password_verify('password123', (string) $dbRow['password']));
    }

    public function testCreateDoesNotSetMustChangePassword(): void
    {
        $row   = $this->makeAdmin();
        $dbRow = $this->repo->findById($row['id']);
        $this->assertSame(0, (int) $dbRow['must_change_password']);
    }

    public function testCreateDoctorRequiresProviderId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('provider_id');

        $this->service->create([
            'username' => 'doc',
            'name'     => 'Dr. X',
            'password' => 'password123',
            'role'     => 'doctor',
            // provider_id intentionally omitted
        ]);
    }

    public function testCreateDoctorLinksProvider(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $row = $this->service->create([
            'username'    => 'doc',
            'name'        => 'Dr. X',
            'password'    => 'password123',
            'role'        => 'doctor',
            'provider_id' => $providerId,
        ]);

        $this->assertSame($providerId, $row['provider_id']);
    }

    public function testCreateNonDoctorIgnoresProviderId(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $row = $this->service->create([
            'username'    => 'rec',
            'name'        => 'Rec User',
            'password'    => 'password123',
            'role'        => 'receptionist',
            'provider_id' => $providerId, // should be ignored for non-doctors
        ]);

        $this->assertNull($row['provider_id']);
    }

    public function testCreateRejectsDuplicateUsername(): void
    {
        $this->makeAdmin('duplicated');

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage("Username 'duplicated' is already in use");

        $this->makeAdmin('duplicated');
    }

    public function testCreateRejectsWeakPassword(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least');

        $this->service->create([
            'username' => 'weakpw',
            'name'     => 'Test',
            'password' => 'short',
            'role'     => 'admin',
        ]);
    }

    public function testCreateRejectsInvalidRole(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('role must be one of');

        $this->service->create([
            'username' => 'whoami',
            'name'     => 'Test',
            'password' => 'password123',
            'role'     => 'superuser',
        ]);
    }

    public function testCreateRejectsMissingUsername(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'username' is required");

        $this->service->create([
            'name'     => 'No Username',
            'password' => 'password123',
            'role'     => 'admin',
        ]);
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function testUpdateChangesName(): void
    {
        $row     = $this->makeAdmin();
        $updated = $this->service->update($row['id'], ['name' => 'New Name']);

        $this->assertSame('New Name', $updated['name']);
        $this->assertSame('admin', $updated['role']); // unchanged
    }

    public function testUpdateDemotingDoctorClearsProviderId(): void
    {
        $providerId = TestSeed::provider($this->pdo);
        $doc = $this->service->create([
            'username'    => 'doc',
            'name'        => 'Dr. X',
            'password'    => 'password123',
            'role'        => 'doctor',
            'provider_id' => $providerId,
        ]);

        $updated = $this->service->update($doc['id'], ['role' => 'receptionist']);

        $this->assertNull($updated['provider_id']);
    }

    public function testUpdatePasswordHashesNewPassword(): void
    {
        $row = $this->makeAdmin(pass: 'firstpass1');
        $this->service->update($row['id'], ['password' => 'secondpass2']);

        $dbRow = $this->repo->findById($row['id']);
        $this->assertTrue(password_verify('secondpass2', (string) $dbRow['password']));
        $this->assertFalse(password_verify('firstpass1', (string) $dbRow['password']));
    }

    public function testUpdateRejectsDuplicateUsernameForAnotherUser(): void
    {
        $a = $this->makeAdmin('alpha');
        $this->makeAdmin('beta');

        $this->expectException(ConflictException::class);

        $this->service->update($a['id'], ['username' => 'beta']);
    }

    public function testUpdateMissingUserThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Staff user does not exist');

        $this->service->update('00000000-0000-0000-0000-000000000000', ['name' => 'X']);
    }

    // ── deactivate ────────────────────────────────────────────────────────────

    public function testDeactivateSetsIsActiveZero(): void
    {
        $row = $this->makeAdmin();
        $this->service->deactivate($row['id']);

        $dbRow = $this->repo->findById($row['id'], includeInactive: true);
        $this->assertSame(0, (int) $dbRow['is_active']);

        // Active-only lookup returns null.
        $this->assertNull($this->repo->findById($row['id']));
    }

    public function testDeactivateMissingUserThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Staff user does not exist');

        $this->service->deactivate('00000000-0000-0000-0000-000000000000');
    }

    // ── changePassword ────────────────────────────────────────────────────────

    public function testChangePasswordSuccessful(): void
    {
        $row = $this->makeAdmin(pass: 'oldpass123');

        $this->service->changePassword($row['id'], 'oldpass123', 'newpass456');

        $dbRow = $this->repo->findById($row['id']);
        $this->assertTrue(password_verify('newpass456', (string) $dbRow['password']));
        $this->assertFalse(password_verify('oldpass123', (string) $dbRow['password']));
        // must_change_password must be cleared.
        $this->assertSame(0, (int) $dbRow['must_change_password']);
    }

    public function testChangePasswordClearsMustChangeFlag(): void
    {
        // Seed directly with must_change_password = 1 (simulates first-login admin).
        $userId = TestSeed::staffUser(
            $this->pdo,
            username: 'mustchange',
            plainPassword: 'firsttime1',
            mustChangePassword: 1,
        );

        $this->service->changePassword($userId, 'firsttime1', 'newsecure9');

        $dbRow = $this->repo->findById($userId);
        $this->assertSame(0, (int) $dbRow['must_change_password']);
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $row = $this->makeAdmin(pass: 'correct123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        $this->service->changePassword($row['id'], 'wrong1234', 'newpass456');
    }

    public function testChangePasswordRejectsSamePassword(): void
    {
        $row = $this->makeAdmin(pass: 'samepass1');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('New password must differ');

        $this->service->changePassword($row['id'], 'samepass1', 'samepass1');
    }

    public function testChangePasswordRejectsWeakNewPassword(): void
    {
        $row = $this->makeAdmin(pass: 'current123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least');

        $this->service->changePassword($row['id'], 'current123', 'short');
    }
}

<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\AuthorizationException;
use Clinic\Repository\StaffRepository;
use Clinic\Service\AuthService;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for AuthService::requireRole().
 *
 * All tests work with fake stdClass JWTs so no DB seeding is needed
 * (though AuthService requires a StaffRepository for issueTokenForUser,
 * we never call that method here).
 */
final class AuthorizationTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->auth = new AuthService(new StaffRepository(Connection::get()));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function jwt(string $role): stdClass
    {
        $jwt       = new stdClass();
        $jwt->role = $role;
        return $jwt;
    }

    // ── allowed ───────────────────────────────────────────────────────────────

    public function testAdminPassesSingleRoleCheck(): void
    {
        $this->auth->requireRole($this->jwt('admin'), 'admin');
        $this->assertTrue(true); // silence "no assertions" warning
    }

    public function testReceptionistPassesMultiRoleCheck(): void
    {
        $this->auth->requireRole($this->jwt('receptionist'), 'admin', 'receptionist');
        $this->assertTrue(true);
    }

    public function testDoctorPassesWhenDoctorIsAllowed(): void
    {
        $this->auth->requireRole($this->jwt('doctor'), 'doctor');
        $this->assertTrue(true);
    }

    public function testAdminPassesWhenMultipleRolesAllowed(): void
    {
        $this->auth->requireRole($this->jwt('admin'), 'admin', 'receptionist', 'doctor');
        $this->assertTrue(true);
    }

    // ── denied ────────────────────────────────────────────────────────────────

    public function testDoctorDeniedAdminOnlyEndpoint(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->auth->requireRole($this->jwt('doctor'), 'admin');
    }

    public function testReceptionistDeniedAdminOnlyEndpoint(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->auth->requireRole($this->jwt('receptionist'), 'admin');
    }

    public function testAdminDeniedDoctorOnlyEndpoint(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->auth->requireRole($this->jwt('admin'), 'doctor');
    }

    public function testDoctorDeniedReceptionistOnlyEndpoint(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->auth->requireRole($this->jwt('doctor'), 'receptionist');
    }

    public function testUnknownRoleAlwaysDenied(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->auth->requireRole($this->jwt('guest'), 'admin', 'receptionist', 'doctor');
    }

    // ── error code ────────────────────────────────────────────────────────────

    public function testDeniedExceptionCarriesForbiddenCode(): void
    {
        try {
            $this->auth->requireRole($this->jwt('doctor'), 'admin');
            $this->fail('Expected AuthorizationException was not thrown');
        } catch (AuthorizationException $e) {
            $this->assertSame('FORBIDDEN', $e->getErrorCode());
            $this->assertStringContainsString('admin', $e->getMessage());
        }
    }
}

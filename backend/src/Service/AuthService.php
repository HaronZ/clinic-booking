<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\AuthorizationException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\StaffRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

final class AuthService
{
    private string $secret;
    private int    $ttl;    // seconds

    public function __construct(
        private readonly StaffRepository $staff,
    ) {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'change-me-in-production-please';
        $this->ttl    = (int) ($_ENV['JWT_TTL_HOURS'] ?? 12) * 3600;
    }

    /**
     * Validate credentials and return a signed JWT + staff info.
     *
     * @return array{token:string,staff:array<string,mixed>}
     */
    public function login(string $username, string $password): array
    {
        $user = $this->staff->findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            throw new ValidationException('INVALID_CREDENTIALS', 'Invalid username or password');
        }

        $now     = time();
        $payload = [
            'iat'         => $now,
            'exp'         => $now + $this->ttl,
            'sub'         => $user['id'],
            'role'        => $user['role'],
            'provider_id' => $user['provider_id'], // null for admin/receptionist
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');

        return [
            'token' => $token,
            'staff' => [
                'id'          => $user['id'],
                'name'        => $user['name'],
                'role'        => $user['role'],
                'provider_id' => $user['provider_id'],
            ],
        ];
    }

    /**
     * Decode and validate a Bearer token from the Authorization header.
     * Returns the decoded payload or throws ValidationException.
     */
    public function requireAuth(): stdClass
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            throw new ValidationException('UNAUTHORIZED', 'Missing or invalid Authorization header');
        }

        $token = substr($header, 7);
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            throw new ValidationException('UNAUTHORIZED', 'Invalid or expired token');
        }
    }

    /**
     * Assert that the JWT subject has one of the allowed roles.
     * Throws AuthorizationException (HTTP 403) otherwise.
     *
     * Usage:
     *   $jwt = $authSvc->requireAuth();
     *   $authSvc->requireRole($jwt, 'admin');
     *   // or accept multiple:
     *   $authSvc->requireRole($jwt, 'admin', 'receptionist');
     */
    public function requireRole(stdClass $jwt, string ...$allowedRoles): void
    {
        $role = $jwt->role ?? null;
        if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
            throw new AuthorizationException(
                'FORBIDDEN',
                'This action requires one of: ' . implode(', ', $allowedRoles),
            );
        }
    }
}

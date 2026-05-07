<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ConflictException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Repository\StaffRepository;
use Ramsey\Uuid\Uuid;

/**
 * Staff account management for the admin panel + the change-password flow
 * available to every authenticated user.
 *
 * Password rules (intentionally light for an MVP — the README documents this):
 *   - Min 8 characters
 *   - Cannot equal the current password (on change-password)
 *   - Stored only as bcrypt cost-12 hash
 */
final class StaffManagementService
{
    private const MAX_USERNAME_LEN = 80;
    private const MAX_NAME_LEN     = 120;
    private const MIN_PASSWORD_LEN = 8;
    private const VALID_ROLES      = ['admin', 'receptionist', 'doctor'];
    private const BCRYPT_COST      = 12;

    public function __construct(
        private readonly StaffRepository $staff,
        private readonly ProviderRepository $providers,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function listAll(bool $includeInactive = false): array
    {
        return $this->staff->findAll($includeInactive);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> Created row, password hash never included.
     */
    public function create(array $input): array
    {
        $username = $this->requireString($input, 'username', self::MAX_USERNAME_LEN);
        $name     = $this->requireString($input, 'name',     self::MAX_NAME_LEN);
        $password = $this->requirePassword($input, 'password');
        $role     = $this->requireRole($input);
        $providerId = $this->resolveProviderId($input, $role);

        if ($this->staff->usernameExists($username)) {
            throw new ConflictException('USERNAME_TAKEN', "Username '{$username}' is already in use");
        }

        $id = Uuid::uuid4()->toString();
        $this->staff->insert([
            'id'                   => $id,
            'username'             => $username,
            'name'                 => $name,
            'password'             => password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            'provider_id'          => $providerId,
            'role'                 => $role,
            'must_change_password' => 0, // creator chose the password — no need to force change
        ]);

        return $this->loadSafe($id);
    }

    /**
     * @param array<string,mixed> $input Allowed: username, name, role, provider_id, password
     */
    public function update(string $id, array $input): array
    {
        $existing = $this->staff->findById($id, includeInactive: true);
        if ($existing === null) {
            throw new ValidationException('STAFF_NOT_FOUND', 'Staff user does not exist');
        }

        $patch = [];
        if (array_key_exists('username', $input)) {
            $username = $this->requireString($input, 'username', self::MAX_USERNAME_LEN);
            if ($this->staff->usernameExists($username, excludeId: $id)) {
                throw new ConflictException('USERNAME_TAKEN', "Username '{$username}' is already in use");
            }
            $patch['username'] = $username;
        }
        if (array_key_exists('name', $input)) {
            $patch['name'] = $this->requireString($input, 'name', self::MAX_NAME_LEN);
        }
        if (array_key_exists('role', $input)) {
            $patch['role'] = $this->requireRole($input);
        }
        if (array_key_exists('provider_id', $input)) {
            $effectiveRole = $patch['role'] ?? (string) $existing['role'];
            $patch['provider_id'] = $this->resolveProviderId($input, $effectiveRole);
        } elseif (isset($patch['role']) && $patch['role'] !== 'doctor') {
            // Demoting a doctor → clear linked provider so it doesn't dangle.
            $patch['provider_id'] = null;
        }

        if ($patch !== []) {
            $this->staff->update($id, $patch);
        }

        if (array_key_exists('password', $input)) {
            $newPassword = $this->requirePassword($input, 'password');
            $this->staff->updatePassword(
                $id,
                password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
                clearMustChange: true,
            );
        }

        return $this->loadSafe($id);
    }

    public function deactivate(string $id): void
    {
        if ($this->staff->findById($id, includeInactive: true) === null) {
            throw new ValidationException('STAFF_NOT_FOUND', 'Staff user does not exist');
        }
        $this->staff->setActive($id, false);
    }

    /**
     * Change-password endpoint, available to every authenticated user.
     * Verifies the current password, validates the new one, and re-hashes.
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $row = $this->staff->findById($userId, includeInactive: true);
        if ($row === null) {
            throw new ValidationException('STAFF_NOT_FOUND', 'Account no longer exists');
        }
        if (!password_verify($currentPassword, (string) $row['password'])) {
            throw new ValidationException(
                'INVALID_CURRENT_PASSWORD',
                'Current password is incorrect',
            );
        }
        if (strlen($newPassword) < self::MIN_PASSWORD_LEN) {
            throw new ValidationException(
                'WEAK_PASSWORD',
                'New password must be at least ' . self::MIN_PASSWORD_LEN . ' characters',
            );
        }
        if ($newPassword === $currentPassword) {
            throw new ValidationException(
                'SAME_PASSWORD',
                'New password must differ from the current one',
            );
        }

        $this->staff->updatePassword(
            $userId,
            password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            clearMustChange: true,
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Reload a staff row and strip the password hash before returning.
     *
     * @return array<string,mixed>
     */
    private function loadSafe(string $id): array
    {
        $row = $this->staff->findById($id, includeInactive: true);
        if ($row === null) {
            throw new ValidationException('SERVER_ERROR', 'Staff disappeared after write');
        }
        unset($row['password']);
        return $row;
    }

    /** @param array<string,mixed> $input */
    private function requireString(array $input, string $field, int $max): string
    {
        $raw = $input[$field] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            throw new ValidationException('MISSING_FIELD', "Field '{$field}' is required");
        }
        $val = trim($raw);
        if (mb_strlen($val) > $max) {
            throw new ValidationException('FIELD_TOO_LONG', "Field '{$field}' must be at most {$max} characters");
        }
        return $val;
    }

    /** @param array<string,mixed> $input */
    private function requirePassword(array $input, string $field): string
    {
        $raw = $input[$field] ?? null;
        if (!is_string($raw) || $raw === '') {
            throw new ValidationException('MISSING_FIELD', "Field '{$field}' is required");
        }
        if (strlen($raw) < self::MIN_PASSWORD_LEN) {
            throw new ValidationException(
                'WEAK_PASSWORD',
                "Password must be at least " . self::MIN_PASSWORD_LEN . ' characters',
            );
        }
        return $raw;
    }

    /** @param array<string,mixed> $input */
    private function requireRole(array $input): string
    {
        $role = $input['role'] ?? null;
        if (!is_string($role) || !in_array($role, self::VALID_ROLES, true)) {
            throw new ValidationException(
                'INVALID_ROLE',
                'role must be one of: ' . implode(', ', self::VALID_ROLES),
            );
        }
        return $role;
    }

    /**
     * @param array<string,mixed> $input
     */
    private function resolveProviderId(array $input, string $role): ?string
    {
        $providerId = $input['provider_id'] ?? null;

        if ($role === 'doctor') {
            if (!is_string($providerId) || $providerId === '') {
                throw new ValidationException(
                    'MISSING_PROVIDER',
                    'doctor accounts must be linked to a provider via provider_id',
                );
            }
            $provider = $this->providers->findById($providerId, includeInactive: true);
            if ($provider === null) {
                throw new ValidationException(
                    'PROVIDER_NOT_FOUND',
                    'Linked provider does not exist',
                );
            }
            return $providerId;
        }

        // Non-doctor roles never carry a provider link.
        return null;
    }
}

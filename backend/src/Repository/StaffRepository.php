<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class StaffRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, name, password, provider_id, role
               FROM staff_users
              WHERE username  = :username
                AND is_active = 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, name, provider_id, role
               FROM staff_users
              WHERE id        = :id
                AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

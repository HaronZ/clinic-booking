<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class StaffRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Used by login. Includes password hash and must_change_password flag.
     *
     * @return array<string,mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, name, password, provider_id, role,
                    is_active, must_change_password
               FROM staff_users
              WHERE username  = :username
                AND is_active = 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findById(string $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT id, username, name, password, provider_id, role,
                       is_active, must_change_password
                  FROM staff_users
                 WHERE id = :id';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Admin-panel listing. Password hashes are excluded — never returned over HTTP.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, username, name, provider_id, role,
                       is_active, must_change_password
                  FROM staff_users';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY role, name';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function usernameExists(string $username, ?string $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM staff_users WHERE username = :u LIMIT 1'
            );
            $stmt->execute(['u' => $username]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM staff_users WHERE username = :u AND id <> :id LIMIT 1'
            );
            $stmt->execute(['u' => $username, 'id' => $excludeId]);
        }
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{
     *   id:string, username:string, name:string, password:string,
     *   role:string, provider_id:?string, must_change_password?:int
     * } $row
     */
    public function insert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO staff_users
                   (id, username, name, password, provider_id, role,
                    is_active, must_change_password)
                  VALUES (:id, :u, :n, :p, :pid, :r, 1, :mcp)'
        );
        $stmt->execute([
            'id'  => $row['id'],
            'u'   => $row['username'],
            'n'   => $row['name'],
            'p'   => $row['password'],
            'pid' => $row['provider_id'],
            'r'   => $row['role'],
            'mcp' => $row['must_change_password'] ?? 0,
        ]);
    }

    /**
     * Partial update. Allowed: username, name, role, provider_id.
     * Password is updated separately via updatePassword().
     *
     * @param array<string,mixed> $row
     */
    public function update(string $id, array $row): void
    {
        $allowed = ['username', 'name', 'role', 'provider_id'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $row)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $row[$col];
            }
        }
        if ($sets === []) return;

        $sql = 'UPDATE staff_users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function updatePassword(string $id, string $hash, bool $clearMustChange): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE staff_users
                SET password = :p,
                    must_change_password = :mcp
              WHERE id = :id'
        );
        $stmt->execute([
            'id'  => $id,
            'p'   => $hash,
            'mcp' => $clearMustChange ? 0 : 1,
        ]);
    }

    public function setActive(string $id, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE staff_users SET is_active = :a WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'a' => $active ? 1 : 0]);
    }
}

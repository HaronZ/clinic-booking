<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class ProviderRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<string,mixed>|null Returns null if not found OR is_active = 0.
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, specialty, slug, is_active
               FROM providers
              WHERE id = :id
                AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<int,array<string,mixed>> Active providers only.
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, specialty, slug
               FROM providers
              WHERE is_active = 1
              ORDER BY name'
        );
        return $stmt->fetchAll();
    }
}

<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

/**
 * CRUD on appointment_types. Until now this table was queried inline from
 * index.php / AvailabilityRepository — admin panel needs a proper repo.
 */
final class AppointmentTypeRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findById(string $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT id, name, slug, duration_minutes, is_active
                  FROM appointment_types
                 WHERE id = :id';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, name, slug, duration_minutes, is_active
                  FROM appointment_types';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY duration_minutes';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function slugExists(string $slug, ?string $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM appointment_types WHERE slug = :slug LIMIT 1'
            );
            $stmt->execute(['slug' => $slug]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM appointment_types WHERE slug = :slug AND id <> :id LIMIT 1'
            );
            $stmt->execute(['slug' => $slug, 'id' => $excludeId]);
        }
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{id:string,name:string,slug:string,duration_minutes:int} $row
     */
    public function insert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO appointment_types (id, name, slug, duration_minutes, is_active)
                  VALUES (:id, :name, :slug, :duration, 1)'
        );
        $stmt->execute([
            'id'       => $row['id'],
            'name'     => $row['name'],
            'slug'     => $row['slug'],
            'duration' => $row['duration_minutes'],
        ]);
    }

    /**
     * @param array<string,mixed> $row Allowed: name, slug, duration_minutes
     */
    public function update(string $id, array $row): void
    {
        $allowed = ['name', 'slug', 'duration_minutes'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $row)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $row[$col];
            }
        }
        if ($sets === []) return;

        $sql = 'UPDATE appointment_types SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(string $id, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE appointment_types SET is_active = :a WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'a' => $active ? 1 : 0]);
    }
}

<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class ProviderRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * By default returns only active providers (preserves the original
     * behaviour relied on by booking + availability code paths).
     * Pass $includeInactive = true for admin views.
     *
     * @return array<string,mixed>|null
     */
    public function findById(string $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT id, name, specialty, slug, is_active
                  FROM providers
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
     * @return array<int,array<string,mixed>>
     */
    public function findAll(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, name, specialty, slug, is_active
                  FROM providers';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Used by the management service to detect collisions before insert/update.
     * If $excludeId is provided, that row is ignored (so a provider can keep
     * its existing slug during an update).
     */
    public function slugExists(string $slug, ?string $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM providers WHERE slug = :slug LIMIT 1'
            );
            $stmt->execute(['slug' => $slug]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM providers WHERE slug = :slug AND id <> :id LIMIT 1'
            );
            $stmt->execute(['slug' => $slug, 'id' => $excludeId]);
        }
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{id:string,name:string,specialty:string,slug:string} $row
     */
    public function insert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO providers (id, name, specialty, slug, is_active)
                  VALUES (:id, :name, :specialty, :slug, 1)'
        );
        $stmt->execute([
            'id'        => $row['id'],
            'name'      => $row['name'],
            'specialty' => $row['specialty'],
            'slug'      => $row['slug'],
        ]);
    }

    /**
     * Partial update: only the keys present in $row are changed.
     *
     * @param array<string,string> $row Allowed keys: name, specialty, slug
     */
    public function update(string $id, array $row): void
    {
        $allowed = ['name', 'specialty', 'slug'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $row)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $row[$col];
            }
        }

        if ($sets === []) {
            return; // nothing to update
        }

        $sql = 'UPDATE providers SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(string $id, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE providers SET is_active = :active WHERE id = :id'
        );
        $stmt->execute([
            'id'     => $id,
            'active' => $active ? 1 : 0,
        ]);
    }
}

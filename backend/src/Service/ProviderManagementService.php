<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ValidationException;
use Clinic\Repository\ProviderRepository;
use Clinic\Util\Slug;
use Ramsey\Uuid\Uuid;

/**
 * Provider (doctor) management for the admin panel.
 *
 * Conventions:
 *   - Slugs are auto-generated server-side from the name when not supplied.
 *     On collision, "-2", "-3" suffixes are added.
 *   - Soft-delete only (is_active = 0). A provider with historical
 *     appointments cannot be hard-deleted because of FK constraints.
 *   - All inputs come through ValidationException on bad data.
 */
final class ProviderManagementService
{
    private const MAX_NAME_LEN      = 120;
    private const MAX_SPECIALTY_LEN = 120;
    private const MAX_SLUG_LEN      = 80;

    public function __construct(
        private readonly ProviderRepository $providers,
    ) {}

    /**
     * @param array<string,mixed> $input Required: name, specialty. Optional: slug.
     * @return array<string,mixed>       The created provider row.
     */
    public function create(array $input): array
    {
        $name      = $this->requireString($input, 'name', self::MAX_NAME_LEN);
        $specialty = $this->requireString($input, 'specialty', self::MAX_SPECIALTY_LEN);
        $slug      = $this->resolveSlug($input['slug'] ?? null, $name, excludeId: null);

        $id = Uuid::uuid4()->toString();
        $this->providers->insert([
            'id'        => $id,
            'name'      => $name,
            'specialty' => $specialty,
            'slug'      => $slug,
        ]);

        $row = $this->providers->findById($id, includeInactive: true);
        if ($row === null) {
            // Should never happen since we just inserted.
            throw new ValidationException('SERVER_ERROR', 'Provider could not be loaded after insert');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $input Any of: name, specialty, slug.
     * @return array<string,mixed>       The updated provider row.
     */
    public function update(string $id, array $input): array
    {
        $existing = $this->providers->findById($id, includeInactive: true);
        if ($existing === null) {
            throw new ValidationException('PROVIDER_NOT_FOUND', 'Provider does not exist');
        }

        $patch = [];
        if (array_key_exists('name', $input)) {
            $patch['name'] = $this->requireString($input, 'name', self::MAX_NAME_LEN);
        }
        if (array_key_exists('specialty', $input)) {
            $patch['specialty'] = $this->requireString($input, 'specialty', self::MAX_SPECIALTY_LEN);
        }
        if (array_key_exists('slug', $input)) {
            // Caller wants a specific slug — still resolve through collision check.
            $patch['slug'] = $this->resolveSlug(
                (string) $input['slug'],
                $patch['name'] ?? (string) $existing['name'],
                excludeId: $id,
            );
        }

        if ($patch !== []) {
            $this->providers->update($id, $patch);
        }

        $row = $this->providers->findById($id, includeInactive: true);
        if ($row === null) {
            throw new ValidationException('SERVER_ERROR', 'Provider disappeared during update');
        }
        return $row;
    }

    public function deactivate(string $id): void
    {
        $existing = $this->providers->findById($id, includeInactive: true);
        if ($existing === null) {
            throw new ValidationException('PROVIDER_NOT_FOUND', 'Provider does not exist');
        }
        $this->providers->setActive($id, false);
    }

    public function reactivate(string $id): void
    {
        $existing = $this->providers->findById($id, includeInactive: true);
        if ($existing === null) {
            throw new ValidationException('PROVIDER_NOT_FOUND', 'Provider does not exist');
        }
        $this->providers->setActive($id, true);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Validate and trim a required string field; throws ValidationException
     * on missing / empty / overlength.
     *
     * @param array<string,mixed> $input
     */
    private function requireString(array $input, string $field, int $max): string
    {
        $raw = $input[$field] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            throw new ValidationException(
                'MISSING_FIELD',
                "Field '{$field}' is required",
            );
        }
        $val = trim($raw);
        if (mb_strlen($val) > $max) {
            throw new ValidationException(
                'FIELD_TOO_LONG',
                "Field '{$field}' must be at most {$max} characters",
            );
        }
        return $val;
    }

    /**
     * Resolve the final slug:
     *   - If $supplied is a non-empty string, slugify it (so we still normalize it).
     *   - Otherwise derive from $name.
     *   - On collision, append "-2", "-3", ... up to a sanity limit.
     */
    private function resolveSlug(mixed $supplied, string $name, ?string $excludeId): string
    {
        $base = (is_string($supplied) && trim($supplied) !== '')
            ? Slug::fromName($supplied)
            : Slug::fromName($name);

        if (mb_strlen($base) > self::MAX_SLUG_LEN) {
            $base = mb_substr($base, 0, self::MAX_SLUG_LEN);
        }

        $candidate = $base;
        $suffix    = 2;
        while ($this->providers->slugExists($candidate, $excludeId)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 100) {
                throw new ValidationException(
                    'SLUG_TAKEN',
                    'Could not generate a unique slug — please choose a different name',
                );
            }
        }
        return $candidate;
    }
}

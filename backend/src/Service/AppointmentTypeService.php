<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentTypeRepository;
use Clinic\Util\Slug;
use Ramsey\Uuid\Uuid;

/**
 * CRUD on appointment_types. Mirrors ProviderManagementService.
 *
 * duration_minutes is constrained to (0, 480] to match the existing
 * CHECK constraint in schema.sql.
 */
final class AppointmentTypeService
{
    private const MAX_NAME_LEN = 120;
    private const MAX_SLUG_LEN = 80;
    private const MIN_DURATION = 1;
    private const MAX_DURATION = 480;

    public function __construct(
        private readonly AppointmentTypeRepository $types,
    ) {}

    /** @param array<string,mixed> $input */
    public function create(array $input): array
    {
        $name     = $this->requireString($input, 'name', self::MAX_NAME_LEN);
        $duration = $this->requireDuration($input);
        $slug     = $this->resolveSlug($input['slug'] ?? null, $name, excludeId: null);

        $id = Uuid::uuid4()->toString();
        $this->types->insert([
            'id'               => $id,
            'name'             => $name,
            'slug'             => $slug,
            'duration_minutes' => $duration,
        ]);

        $row = $this->types->findById($id, includeInactive: true);
        if ($row === null) {
            throw new ValidationException('SERVER_ERROR', 'Type could not be loaded after insert');
        }
        return $row;
    }

    /** @param array<string,mixed> $input */
    public function update(string $id, array $input): array
    {
        $existing = $this->types->findById($id, includeInactive: true);
        if ($existing === null) {
            throw new ValidationException('TYPE_NOT_FOUND', 'Appointment type does not exist');
        }

        $patch = [];
        if (array_key_exists('name', $input)) {
            $patch['name'] = $this->requireString($input, 'name', self::MAX_NAME_LEN);
        }
        if (array_key_exists('duration_minutes', $input)) {
            $patch['duration_minutes'] = $this->requireDuration($input);
        }
        if (array_key_exists('slug', $input)) {
            $patch['slug'] = $this->resolveSlug(
                (string) $input['slug'],
                $patch['name'] ?? (string) $existing['name'],
                excludeId: $id,
            );
        }

        if ($patch !== []) {
            $this->types->update($id, $patch);
        }

        $row = $this->types->findById($id, includeInactive: true);
        if ($row === null) {
            throw new ValidationException('SERVER_ERROR', 'Type disappeared during update');
        }
        return $row;
    }

    public function deactivate(string $id): void
    {
        if ($this->types->findById($id, includeInactive: true) === null) {
            throw new ValidationException('TYPE_NOT_FOUND', 'Appointment type does not exist');
        }
        $this->types->setActive($id, false);
    }

    public function reactivate(string $id): void
    {
        if ($this->types->findById($id, includeInactive: true) === null) {
            throw new ValidationException('TYPE_NOT_FOUND', 'Appointment type does not exist');
        }
        $this->types->setActive($id, true);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

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
    private function requireDuration(array $input): int
    {
        $raw = $input['duration_minutes'] ?? null;
        if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
            throw new ValidationException(
                'INVALID_DURATION',
                'duration_minutes must be a positive integer',
            );
        }
        $n = (int) $raw;
        if ($n < self::MIN_DURATION || $n > self::MAX_DURATION) {
            throw new ValidationException(
                'INVALID_DURATION',
                'duration_minutes must be between ' . self::MIN_DURATION . ' and ' . self::MAX_DURATION,
            );
        }
        return $n;
    }

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
        while ($this->types->slugExists($candidate, $excludeId)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 100) {
                throw new ValidationException(
                    'SLUG_TAKEN',
                    'Could not generate a unique slug — choose a different name',
                );
            }
        }
        return $candidate;
    }
}

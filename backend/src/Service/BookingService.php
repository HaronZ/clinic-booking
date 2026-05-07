<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\ConflictException;
use Clinic\Exception\InvalidTransitionException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\ProviderRepository;
use Clinic\Service\EmailService;
use DateTimeImmutable;
use PDO;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Booking creation + status transitions.
 *
 * Hard rules (also documented in CLAUDE.md):
 *   - end_time is derived server-side: start_time + appointment_type.duration_minutes
 *   - Client-supplied end_time / duration_minutes is silently ignored
 *   - Double-booking is prevented via SELECT ... FOR UPDATE in a transaction
 *   - patient_name / patient_email are PII and never logged or returned in success payload
 *   - Status transitions are an explicit allow-list
 */
final class BookingService
{
    /**
     * Allow-list of valid status transitions.
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProviderRepository $providers,
        private readonly AppointmentRepository $appointments,
        private readonly EmailService $email = new EmailService(),
    ) {}

    /**
     * Create a booking. Throws ValidationException, ConflictException, or
     * lets transaction errors bubble up after rollback.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed> Sanitized payload — no PII fields.
     */
    public function create(array $input): array
    {
        // ---- a) required-field check ----
        foreach (['provider_id', 'appointment_type_id', 'start_time', 'patient_name', 'patient_phone'] as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                throw new ValidationException(
                    'MISSING_FIELD',
                    sprintf('Missing required field: %s', $field),
                );
            }
        }

        // ---- b) parse start_time ----
        $startStr = (string) $input['start_time'];
        $startDt  = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $startStr);
        if ($startDt === false) {
            throw new ValidationException(
                'INVALID_DATETIME',
                'start_time must be in format YYYY-MM-DDTHH:MM:SS',
            );
        }

        // ---- c) provider lookup (active only) ----
        $providerId = (string) $input['provider_id'];
        $provider   = $this->providers->findById($providerId);
        if ($provider === null) {
            throw new ValidationException(
                'PROVIDER_NOT_FOUND',
                'Provider does not exist or is inactive',
            );
        }

        // ---- d) appointment_type lookup (active only) ----
        $typeId = (string) $input['appointment_type_id'];
        $type   = $this->loadActiveType($typeId);
        if ($type === null) {
            throw new ValidationException(
                'TYPE_NOT_FOUND',
                'Appointment type does not exist or is inactive',
            );
        }

        // ---- e) derive end_time server-side ----
        $duration = (int) $type['duration_minutes'];
        $endDt    = $startDt->modify(sprintf('+%d minutes', $duration));
        $startSql = $startDt->format('Y-m-d H:i:s');
        $endSql   = $endDt->format('Y-m-d H:i:s');

        // ---- f) provider must be working that day, slot must fit in window ----
        $dayOfWeek = (int) $startDt->format('w'); // 0=Sun ... 6=Sat
        $schedule  = $this->loadSchedule($providerId, $dayOfWeek);
        if ($schedule === null) {
            throw new ValidationException(
                'OUTSIDE_SCHEDULE',
                'Provider does not work on that day',
            );
        }
        $slotStart = $startDt->format('H:i:s');
        $slotEnd   = $endDt->format('H:i:s');
        if ($slotStart < $schedule['start_time'] || $slotEnd > $schedule['end_time']) {
            throw new ValidationException(
                'OUTSIDE_SCHEDULE',
                'Requested time falls outside provider working hours',
            );
        }

        // ---- g–k) transactional insert ----
        $this->pdo->beginTransaction();
        try {
            $existing = $this->appointments->findOverlappingForUpdate(
                $providerId,
                $startSql,
                $endSql,
            );
            if (count($existing) > 0) {
                $this->pdo->rollBack();
                throw new ConflictException(
                    'TIME_SLOT_TAKEN',
                    'The requested time slot conflicts with an existing booking',
                );
            }

            $id = Uuid::uuid4()->toString();
            $row = [
                'id'                  => $id,
                'provider_id'         => $providerId,
                'appointment_type_id' => $typeId,
                'patient_name'        => (string) $input['patient_name'],
                'patient_email'       => isset($input['patient_email']) && $input['patient_email'] !== ''
                                            ? (string) $input['patient_email']
                                            : null,
                'patient_phone'       => (string) $input['patient_phone'],
                'patient_notes'       => isset($input['patient_notes']) && $input['patient_notes'] !== ''
                                            ? (string) $input['patient_notes']
                                            : null,
                'start_time'          => $startSql,
                'end_time'            => $endSql,
                'status'              => 'pending',
            ];

            $this->appointments->insert($row);
            $this->pdo->commit();
        } catch (ConflictException $e) {
            // already rolled back above — rethrow for the controller
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Privacy: only log opaque IDs, never PII.
            error_log(sprintf(
                '[BookingService::create] provider_id=%s failed: %s',
                $providerId,
                $e->getMessage(),
            ));
            throw $e;
        }

        // ---- l) send confirmation email (non-blocking, MAIL_ENABLED=0 in dev) ----
        $this->email->sendBookingConfirmation([
            'id'            => $id,
            'patient_name'  => $row['patient_name'],
            'patient_email' => $row['patient_email'],
            'provider_name' => $provider['name'],
            'type_name'     => $type['name'],
            'start_time'    => $startSql,
            'end_time'      => $endSql,
        ]);

        // ---- m) sanitized response — no PII ----
        return [
            'id'                  => $id,
            'provider_id'         => $providerId,
            'appointment_type_id' => $typeId,
            'start_time'          => $startSql,
            'end_time'            => $endSql,
            'status'              => 'pending',
            'created_at'          => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Allow-list status transition. Throws InvalidTransitionException if
     * (from, to) is not in self::TRANSITIONS[from].
     */
    public function transition(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? null;
        if ($allowed === null || !in_array($to, $allowed, true)) {
            throw new InvalidTransitionException($from, $to);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadActiveType(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, duration_minutes
               FROM appointment_types
              WHERE id = :id
                AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadSchedule(string $providerId, int $dayOfWeek): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT day_of_week, start_time, end_time
               FROM provider_schedules
              WHERE provider_id = :provider_id
                AND day_of_week = :day_of_week'
        );
        $stmt->execute([
            'provider_id' => $providerId,
            'day_of_week' => $dayOfWeek,
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

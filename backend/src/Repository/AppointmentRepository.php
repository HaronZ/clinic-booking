<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class AppointmentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Hydrated read for the GET /api/bookings/{id} confirmation page.
     * Joins provider + appointment_type. PII fields intentionally NOT returned.
     *
     * @return array<string,mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,
                    a.provider_id,
                    a.appointment_type_id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.created_at,
                    p.name      AS provider_name,
                    p.specialty AS provider_specialty,
                    t.name      AS type_name,
                    t.duration_minutes AS type_duration_minutes
               FROM appointments a
               JOIN providers p         ON a.provider_id = p.id
               JOIN appointment_types t ON a.appointment_type_id = t.id
              WHERE a.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Insert a fully-prepared appointment row. The caller (BookingService) is
     * responsible for generating the UUID and deriving end_time.
     *
     * @param array<string,mixed> $row
     */
    public function insert(array $row): void
    {
        $sql = 'INSERT INTO appointments
                  (id, provider_id, appointment_type_id,
                   patient_name, patient_email, patient_phone, patient_notes,
                   start_time, end_time, status)
                VALUES
                  (:id, :provider_id, :appointment_type_id,
                   :patient_name, :patient_email, :patient_phone, :patient_notes,
                   :start_time, :end_time, :status)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id'                  => $row['id'],
            'provider_id'         => $row['provider_id'],
            'appointment_type_id' => $row['appointment_type_id'],
            'patient_name'        => $row['patient_name'],
            'patient_email'       => $row['patient_email'],
            'patient_phone'       => $row['patient_phone'],
            'patient_notes'       => $row['patient_notes'],
            'start_time'          => $row['start_time'],
            'end_time'            => $row['end_time'],
            'status'              => $row['status'],
        ]);
    }

    /**
     * The double-booking guard. Locks any non-cancelled appointments that
     * overlap [$startTime, $endTime] on the given provider.
     *
     * Must be called inside an active transaction. The locked rows are held
     * until COMMIT/ROLLBACK. A concurrent transaction querying the same range
     * will block on the lock.
     *
     * Overlap predicate: existing.start < new.end AND existing.end > new.start
     * (this is the canonical interval-overlap check).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findOverlappingForUpdate(
        string $providerId,
        string $startTime,
        string $endTime,
    ): array {
        $sql = 'SELECT id, start_time, end_time, status
                  FROM appointments
                 WHERE provider_id = :provider_id
                   AND status NOT IN (\'cancelled\')
                   AND start_time < :end_time
                   AND end_time   > :start_time
                 FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_id' => $providerId,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Used by AvailabilityService (Phase 2). Returns non-cancelled bookings
     * for a given provider on a given calendar date.
     *
     * @return array<int,array{start_time:string,end_time:string}>
     */
    public function findBookingsForDate(string $providerId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT start_time, end_time
               FROM appointments
              WHERE provider_id = :provider_id
                AND status NOT IN (\'cancelled\')
                AND DATE(start_time) = :date'
        );
        $stmt->execute([
            'provider_id' => $providerId,
            'date'        => $date,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Staff schedule view — includes PII (patient_name).
     * Called only from authenticated staff endpoints.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findByDateForStaff(string $providerId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.patient_name,
                    a.patient_phone,
                    a.patient_notes,
                    t.name AS type_name,
                    t.duration_minutes AS type_duration_minutes
               FROM appointments a
               JOIN appointment_types t ON a.appointment_type_id = t.id
              WHERE a.provider_id = :provider_id
                AND DATE(a.start_time) = :date
              ORDER BY a.start_time'
        );
        $stmt->execute([
            'provider_id' => $providerId,
            'date'        => $date,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Returns all appointments for a date across all providers (admin/receptionist view).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllByDate(string $date): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.patient_name,
                    a.patient_phone,
                    a.patient_notes,
                    p.name AS provider_name,
                    t.name AS type_name,
                    t.duration_minutes AS type_duration_minutes
               FROM appointments a
               JOIN providers p           ON a.provider_id = p.id
               JOIN appointment_types t   ON a.appointment_type_id = t.id
              WHERE DATE(a.start_time) = :date
              ORDER BY a.start_time'
        );
        $stmt->execute(['date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Update appointment status. Returns true if the row was found and updated.
     */
    public function updateStatus(string $id, string $newStatus): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE appointments SET status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => $newStatus, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}

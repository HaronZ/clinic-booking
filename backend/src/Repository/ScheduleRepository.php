<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;
use Ramsey\Uuid\Uuid;

/**
 * Schedules are edited as a SET, not row-by-row. The admin opens a provider's
 * 7-day grid, makes their changes, hits Save → we wipe and rewrite atomically.
 *
 * The unique (provider_id, day_of_week) constraint makes per-row upsert
 * fiddly; bulk replace is simpler and matches the natural mental model.
 */
final class ScheduleRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<int,array{id:string,day_of_week:int,start_time:string,end_time:string}>
     */
    public function findByProvider(string $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, day_of_week, start_time, end_time
               FROM provider_schedules
              WHERE provider_id = :pid
              ORDER BY day_of_week'
        );
        $stmt->execute(['pid' => $providerId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'id'          => (string) $row['id'],
                'day_of_week' => (int) $row['day_of_week'],
                'start_time'  => (string) $row['start_time'],
                'end_time'    => (string) $row['end_time'],
            ];
        }
        return $out;
    }

    /**
     * Atomically replace all schedule rows for a provider.
     *
     * @param array<int,array{day_of_week:int,start_time:string,end_time:string}> $rows
     */
    public function replaceForProvider(string $providerId, array $rows): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare(
                'DELETE FROM provider_schedules WHERE provider_id = :pid'
            );
            $del->execute(['pid' => $providerId]);

            if ($rows !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO provider_schedules
                          (id, provider_id, day_of_week, start_time, end_time)
                          VALUES (:id, :pid, :dow, :start, :end)'
                );
                foreach ($rows as $r) {
                    $ins->execute([
                        'id'    => Uuid::uuid4()->toString(),
                        'pid'   => $providerId,
                        'dow'   => $r['day_of_week'],
                        'start' => $r['start_time'],
                        'end'   => $r['end_time'],
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

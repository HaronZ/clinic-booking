<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\RateLimitException;
use PDO;

/**
 * Per-key attempt counter with a sliding window, backed by the auth_attempts
 * table. Designed for failed-login throttling but generic enough to gate any
 * action keyed by a string (IP, user id, email, …).
 *
 * Privacy: keys are hashed (SHA-256) before persistence. We never write the
 * raw IP / email to disk. The hash is one-way, so administrators investigating
 * abuse must hash the suspect key themselves to look for matches.
 *
 * Cleanup: each successful record() has a 5% chance of garbage-collecting rows
 * older than 2× the window. Bounded growth without a cron job.
 */
final class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxAttempts    = 5,
        private readonly int $windowSeconds  = 300,
    ) {}

    /**
     * Throws if $key has accumulated $maxAttempts or more failed attempts in
     * the last $windowSeconds. Call this BEFORE the protected action; if it
     * returns silently, the action is allowed to proceed.
     */
    public function enforce(string $key): void
    {
        if ($key === '') {
            return; // can't rate-limit a missing key — fail-open by design
        }

        $count = $this->countRecent($this->hash($key));

        if ($count >= $this->maxAttempts) {
            $minutes = (int) ceil($this->windowSeconds / 60);
            throw new RateLimitException(
                'TOO_MANY_ATTEMPTS',
                "Too many attempts. Try again in {$minutes} minute"
                    . ($minutes === 1 ? '' : 's') . '.',
                $this->windowSeconds,
            );
        }
    }

    /**
     * Records a failed attempt for $key. Call this AFTER the protected action
     * fails — successful actions should NOT be recorded so legit users aren't
     * locked out by their own bursty correct logins.
     */
    public function record(string $key): void
    {
        if ($key === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_attempts (ip_hash, attempt_at) VALUES (:h, NOW())'
        );
        $stmt->execute(['h' => $this->hash($key)]);

        // Probabilistic cleanup: keeps the table bounded without a cron job.
        if (mt_rand(1, 100) <= 5) {
            $this->purgeOlderThan($this->windowSeconds * 2);
        }
    }

    /**
     * Test seam — clears all recorded attempts. Production code should never
     * call this; tests use it between cases for isolation.
     */
    public function reset(): void
    {
        $this->pdo->exec('DELETE FROM auth_attempts');
    }

    private function countRecent(string $hashedKey): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt   = $this->pdo->prepare(
            'SELECT COUNT(*) FROM auth_attempts
              WHERE ip_hash    = :h
                AND attempt_at >= :cutoff'
        );
        $stmt->execute(['h' => $hashedKey, 'cutoff' => $cutoff]);
        return (int) $stmt->fetchColumn();
    }

    private function purgeOlderThan(int $seconds): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - $seconds);
        $stmt   = $this->pdo->prepare(
            'DELETE FROM auth_attempts WHERE attempt_at < :cutoff'
        );
        $stmt->execute(['cutoff' => $cutoff]);
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}

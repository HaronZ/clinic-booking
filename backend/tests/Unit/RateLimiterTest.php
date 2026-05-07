<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Database\Connection;
use Clinic\Exception\RateLimitException;
use Clinic\Service\RateLimiter;
use Clinic\Tests\Support\TestSeed;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RateLimiter against a real test DB.
 *
 * These tests use small, fast windows (60s) and small budgets so the suite
 * stays sub-second even on slow machines. Time-based eviction is verified by
 * directly back-dating rows in auth_attempts rather than sleeping.
 */
final class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->pdo = Connection::get();
        TestSeed::reset($this->pdo);
        $this->limiter = new RateLimiter($this->pdo, maxAttempts: 3, windowSeconds: 60);
        $this->limiter->reset();
    }

    public function testEnforceAllowsCallsUnderTheBudget(): void
    {
        $this->limiter->enforce('203.0.113.5');
        $this->limiter->enforce('203.0.113.5');
        $this->assertTrue(true); // no exception = allowed
    }

    public function testEnforceThrowsAfterMaxAttempts(): void
    {
        $this->limiter->record('203.0.113.5');
        $this->limiter->record('203.0.113.5');
        $this->limiter->record('203.0.113.5');

        $this->expectException(RateLimitException::class);
        $this->limiter->enforce('203.0.113.5');
    }

    public function testThrownExceptionExposesCorrectCodeAndRetryAfter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->record('203.0.113.5');
        }

        try {
            $this->limiter->enforce('203.0.113.5');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('TOO_MANY_ATTEMPTS', $e->getErrorCode());
            $this->assertSame(60, $e->getRetryAfterSeconds());
            $this->assertStringContainsString('Try again', $e->getMessage());
        }
    }

    public function testDifferentKeysAreIsolated(): void
    {
        // Burn through the budget for one IP …
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->record('203.0.113.5');
        }

        // … another IP should still be allowed.
        $this->limiter->enforce('198.51.100.10');
        $this->assertTrue(true);
    }

    public function testEmptyKeyIsFailOpen(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->record(''); // recorded as no-op
        }
        $this->limiter->enforce(''); // never throws
        $this->assertTrue(true);
    }

    public function testRecordsOlderThanWindowDoNotCount(): void
    {
        // Insert three attempts but back-date them past the 60s window.
        $oldTime = date('Y-m-d H:i:s', time() - 3600);
        $hash    = hash('sha256', '203.0.113.5');
        $stmt    = $this->pdo->prepare(
            'INSERT INTO auth_attempts (ip_hash, attempt_at) VALUES (:h, :t)'
        );
        for ($i = 0; $i < 3; $i++) {
            $stmt->execute(['h' => $hash, 't' => $oldTime]);
        }

        // Should still be allowed — those rows are outside the sliding window.
        $this->limiter->enforce('203.0.113.5');
        $this->assertTrue(true);
    }

    public function testRecordedKeyIsHashedNotPlaintext(): void
    {
        $this->limiter->record('203.0.113.5');

        $stmt = $this->pdo->prepare('SELECT ip_hash FROM auth_attempts LIMIT 1');
        $stmt->execute();
        $stored = (string) $stmt->fetchColumn();

        $this->assertSame(64, strlen($stored), 'SHA-256 hex digest is 64 chars');
        $this->assertNotSame('203.0.113.5', $stored, 'raw IP must not be persisted');
        $this->assertSame(hash('sha256', '203.0.113.5'), $stored);
    }

    public function testResetClearsAllRecordedAttempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->record('203.0.113.5');
        }
        $this->limiter->reset();

        // Budget is restored.
        $this->limiter->enforce('203.0.113.5');
        $this->assertTrue(true);
    }
}

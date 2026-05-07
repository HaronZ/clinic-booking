<?php
declare(strict_types=1);

namespace Clinic\Database;

use PDO;
use RuntimeException;

/**
 * PDO factory. Holds one connection per process.
 *
 * Reads DB_* from $_ENV. .env must be loaded by the bootstrap before
 * the first call to Connection::get() (public/index.php and
 * tests/bootstrap.php both do this).
 */
final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // 1. Railway provides MYSQL_URL (or DATABASE_URL) as a full DSN string.
        //    Prefer this so we always use the correct internal credentials.
        $url = self::first(
            ['MYSQL_URL', 'DATABASE_URL', 'MYSQL_PRIVATE_URL'],
            '',
            required: false,
        );

        if ($url !== '') {
            $parts = parse_url($url);
            $host  = $parts['host'] ?? '127.0.0.1';
            $port  = (string) ($parts['port'] ?? 3306);
            $name  = ltrim($parts['path'] ?? '', '/');
            $user  = urldecode($parts['user'] ?? '');
            $pass  = urldecode($parts['pass'] ?? '');
        } else {
            // 2. Fall back to individual env vars (local .env or explicitly-set Railway vars)
            $host = self::first(['DB_HOST', 'MYSQLHOST',     'MYSQL_HOST'],     '127.0.0.1');
            $port = self::first(['DB_PORT', 'MYSQLPORT',     'MYSQL_PORT'],     '3306');
            $name = self::first(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], '');
            $user = self::first(['DB_USER', 'MYSQLUSER',     'MYSQL_USER'],     '');
            $pass = self::first(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD'], '', required: false);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name,
        );

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /**
     * Test-only: replace the global PDO instance. Used by tests/bootstrap.php
     * to point at clinic_booking_test instead of clinic_booking.
     */
    public static function set(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Test-only: clear the singleton so the next get() rebuilds.
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    /**
     * Return the first env var from $keys that is non-empty.
     * Falls back to $default. If required=true and nothing found, throws.
     *
     * @param string[] $keys
     */
    private static function first(
        array  $keys,
        string $default  = '',
        bool   $required = true,
    ): string {
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if ($value !== false && $value !== '') {
                return (string) $value;
            }
        }
        if ($required && $default === '') {
            throw new RuntimeException(
                'Missing required database config. Tried: ' . implode(', ', $keys),
            );
        }
        return $default;
    }
}

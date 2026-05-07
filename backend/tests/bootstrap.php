<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap. Loads .env, swaps DB_NAME to TEST_DB_NAME, builds a PDO
 * pointing at the test database, and registers it as the global Connection.
 *
 * The test DB MUST be migrated before running tests. See README:
 *
 *   mysql -u root -p clinic_booking_test < db/migrations/001_create_providers.sql
 *   ...etc
 */

use Clinic\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Swap the DB name to point at the test schema.
$testDb = $_ENV['TEST_DB_NAME'] ?? 'clinic_booking_test';
$_ENV['DB_NAME']    = $testDb;
$_SERVER['DB_NAME'] = $testDb;
putenv('DB_NAME=' . $testDb);

// Build PDO via Connection so all production code sees the same instance.
Connection::reset();
$pdo = Connection::get();

// Ensure FK checks are on.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Sanity check: we are NOT pointed at the production database.
$current = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($current) || !str_contains($current, 'test')) {
    throw new RuntimeException(sprintf(
        "Refusing to run tests against database '%s' — DB name must contain 'test'.",
        (string) $current,
    ));
}

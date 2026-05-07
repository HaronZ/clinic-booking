<?php
declare(strict_types=1);

/**
 * Database migration runner.
 *
 * Usage:
 *   php backend/scripts/migrate.php           # tables only  (for real clinics)
 *   php backend/scripts/migrate.php --demo    # tables + demo data (for dev/portfolio)
 *
 * Reads DB credentials from .env (local) or environment variables (Railway).
 * Safe to re-run — all statements use IF NOT EXISTS / INSERT IGNORE.
 */

$withDemo = in_array('--demo', $argv ?? [], true);
$root     = dirname(__DIR__); // backend/

// ── Load .env if it exists (local dev) ───────────────────────────────────────
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $val = trim($val, '"\'');
        $_ENV[trim($key)] = $val;
        putenv(trim($key) . '=' . $val);
    }
}

// ── Read DB credentials (supports .env names and Railway MYSQL* names) ────────
function dbenv(array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        $v = $_ENV[$k] ?? getenv($k);
        if ($v !== false && $v !== '') return (string) $v;
    }
    return $default;
}

$host = dbenv(['DB_HOST', 'MYSQLHOST',     'MYSQL_HOST'],     '127.0.0.1');
$port = dbenv(['DB_PORT', 'MYSQLPORT',     'MYSQL_PORT'],     '3306');
$name = dbenv(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE']);
$user = dbenv(['DB_USER', 'MYSQLUSER',     'MYSQL_USER']);
$pass = dbenv(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD']);

if ($name === '' || $user === '') {
    fwrite(STDERR, "ERROR: Database credentials not found in environment.\n");
    fwrite(STDERR, "Set DB_HOST, DB_NAME, DB_USER, DB_PASS in .env (or Railway MYSQL* vars).\n");
    exit(1);
}

echo "Connecting to MySQL at {$host}:{$port}/{$name} ...\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not connect — " . $e->getMessage() . "\n");
    exit(1);
}

// ── Helper: run a SQL file ────────────────────────────────────────────────────
function runSqlFile(PDO $pdo, string $file, string $label): void
{
    if (!is_file($file)) {
        fwrite(STDERR, "ERROR: File not found: {$file}\n");
        exit(1);
    }

    echo "Running {$label} ...\n";

    $statements = array_filter(
        array_map('trim', explode(';', (string) file_get_contents($file))),
        fn(string $s) => $s !== '' && !preg_match('/^--/', $s),
    );

    $count = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            // 1061 = duplicate key name (index already exists) — safe to ignore
            if (str_contains($e->getMessage(), '1061')) continue;
            fwrite(STDERR, "WARNING: " . $e->getMessage() . "\n");
            fwrite(STDERR, "Statement: " . substr($stmt, 0, 120) . "\n\n");
        }
    }

    echo "  → {$count} statements executed.\n";
}

// ── Run schema (tables only — always) ────────────────────────────────────────
runSqlFile($pdo, $root . '/db/schema.sql', 'schema.sql (table structure)');

// ── Run demo seed (optional) ──────────────────────────────────────────────────
if ($withDemo) {
    echo "\n--demo flag detected: loading demo data ...\n";
    runSqlFile($pdo, $root . '/db/seed_demo.sql', 'seed_demo.sql (demo data)');
    echo "\nDemo accounts loaded:\n";
    echo "  admin        / admin123\n";
    echo "  reception    / reception123\n";
    echo "  ana.reyes    / doctor123\n";
    echo "  luis.mendoza / doctor123\n";
} else {
    echo "\nTables are ready. No demo data loaded.\n";
    echo "Add your own providers, appointment types, and staff via MySQL.\n";
}

echo "\nDatabase setup complete.\n";

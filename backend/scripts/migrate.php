<?php
declare(strict_types=1);

/**
 * One-shot migration + seed runner.
 *
 * Usage (Railway shell or local):
 *   php backend/scripts/migrate.php
 *
 * Reads DB credentials from env vars (same names as .env or Railway MySQL plugin).
 * Safe to run multiple times — all statements use IF NOT EXISTS / INSERT IGNORE.
 */

$root = dirname(__DIR__); // backend/

// Load .env if it exists (local dev)
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

// Read DB credentials — supports local (.env) and Railway (MYSQL*) names
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
    fwrite(STDERR, "Set DB_HOST, DB_NAME, DB_USER, DB_PASS (or Railway MYSQL* vars).\n");
    exit(1);
}

echo "Connecting to MySQL at {$host}:{$port}/{$name} ...\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not connect to database: " . $e->getMessage() . "\n");
    exit(1);
}

$schemaFile = $root . '/db/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "ERROR: Schema file not found: {$schemaFile}\n");
    exit(1);
}

echo "Running schema.sql ...\n";

// Split on semicolons, skip blank statements
$sql        = file_get_contents($schemaFile);
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
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

echo "Done — {$count} statements executed.\n";
echo "Database is ready.\n";

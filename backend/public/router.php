<?php
declare(strict_types=1);

/**
 * PHP built-in server router (php -S).
 * Used for both Railway deployment and local combined dev.
 *
 * Rules:
 *   /api/*        → index.php (API front controller)
 *   existing file → return false (built-in server serves it as a static file)
 *   anything else → serve index.html (Angular SPA client-side routing)
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// API requests → PHP front controller
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/index.php';
    return true;
}

// Existing static asset → let the built-in server send it
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// Everything else → Angular SPA shell (handles its own routing in the browser)
$html = __DIR__ . '/index.html';
if (is_file($html)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($html);
    return true;
}

// Angular not built yet
http_response_code(503);
header('Content-Type: text/html');
echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">'
   . '<h2>Frontend not built</h2>'
   . '<p>Run <code>cd frontend && ng build</code> then copy dist into backend/public/.</p>'
   . '</body></html>';
return true;

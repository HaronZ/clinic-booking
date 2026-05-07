<?php
declare(strict_types=1);

namespace Clinic\Http;

/**
 * JSON response writer. Each method exits the script.
 *
 * Envelope:
 *   success: { "data": <payload>, "meta": {} }
 *   error:   { "error": { "code": "...", "message": "..." }, "meta": {} }
 */
final class Response
{
    public static function success(mixed $data, int $status = 200): never
    {
        self::send([
            'data' => $data,
            'meta' => (object) [],
        ], $status);
    }

    public static function error(string $code, string $message, int $status): never
    {
        self::send([
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
            'meta' => (object) [],
        ], $status);
    }

    private static function send(array $body, int $status): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            // Permissive CORS for the Angular dev server. Tighten for prod
            // (e.g. set Access-Control-Allow-Origin to your frontend origin).
            // Methods cover everything the admin panel uses (PATCH/PUT/DELETE
            // for resource updates and soft-delete); Authorization is required
            // for the JWT bearer token to survive cross-origin requests.
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 600');
        }

        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

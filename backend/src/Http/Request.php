<?php
declare(strict_types=1);

namespace Clinic\Http;

/**
 * Thin wrapper over the PHP superglobals + php://input.
 * Tests can construct a Request directly without going through the SAPI.
 */
final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        /** @var array<string,string> */
        private readonly array $query,
        /** @var array<string,mixed> */
        private readonly array $body,
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $body = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, associative: true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        $query = [];
        foreach ($_GET as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v))) {
                $query[$k] = (string) $v;
            }
        }

        return new self($method, $path, $query, $body);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return array<int,string> */
    public function getPathSegments(): array
    {
        $trimmed = trim($this->path, '/');
        if ($trimmed === '') {
            return [];
        }
        return explode('/', $trimmed);
    }

    public function getQueryParam(string $key): ?string
    {
        return $this->query[$key] ?? null;
    }

    /** @return array<string,mixed> */
    public function getBody(): array
    {
        return $this->body;
    }
}

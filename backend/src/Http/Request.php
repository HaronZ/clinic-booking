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
        private readonly string $clientIp = '',
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

        return new self($method, $path, $query, $body, self::extractClientIp($_SERVER));
    }

    /**
     * Best-effort client IP extraction.
     *
     * Behind a trusted proxy (Railway, nginx, etc.) the originating client IP
     * lives in the leftmost X-Forwarded-For entry; REMOTE_ADDR is the proxy
     * itself. With no proxy we fall back to REMOTE_ADDR. X-Forwarded-For can
     * be spoofed when there's no proxy in front, so a malicious caller could
     * dodge the rate limit by rotating the header — that's an acceptable
     * trade-off for an MVP-grade defense.
     *
     * @param array<string,mixed> $server
     */
    private static function extractClientIp(array $server): string
    {
        $xff = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        $remote = $server['REMOTE_ADDR'] ?? '';
        return is_string($remote) ? $remote : '';
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

    /**
     * Best-effort originating client IP, or '' if unknown.
     *
     * Reads the leftmost entry of X-Forwarded-For when present (proxy-aware)
     * and falls back to REMOTE_ADDR. Used by rate limiters; do not rely on
     * this for audit logging since it can be spoofed without a trusted proxy.
     */
    public function getClientIp(): string
    {
        return $this->clientIp;
    }
}

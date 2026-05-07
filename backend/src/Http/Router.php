<?php
declare(strict_types=1);

namespace Clinic\Http;

/**
 * Tiny regex router. Patterns use {name} for path placeholders.
 *
 *   $router->register('GET', '/api/bookings/{id}', fn(Request $r, array $params) => ...)
 *
 * The handler receives the matched Request and an array of named placeholders.
 * On no match: dispatch() responds 404 NOT_FOUND.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,names:array<int,string>,handler:callable}> */
    private array $routes = [];

    public function register(string $method, string $pattern, callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $m) use (&$names): string {
                $names[] = $m[1];
                return '([^/]+)';
            },
            $pattern,
        );
        // Anchor and escape forward slashes for the regex delimiter.
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => $regex,
            'names'   => $names,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        // CORS preflight.
        if ($request->getMethod() === 'OPTIONS') {
            Response::success(null, 204);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->getMethod()) {
                continue;
            }
            if (!preg_match($route['regex'], $request->getPath(), $m)) {
                continue;
            }

            // Build a name => value map from the captured groups.
            $params = [];
            foreach ($route['names'] as $i => $name) {
                $params[$name] = $m[$i + 1];
            }

            ($route['handler'])($request, $params);
            return; // handler should have called Response::* (which exits).
        }

        Response::error(
            'NOT_FOUND',
            sprintf('No route for %s %s', $request->getMethod(), $request->getPath()),
            404,
        );
    }
}

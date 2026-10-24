<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Middleware : classe avec handle(array $params): void, instanciee sans argument.
 * Lever une exception ou appeler Response::error() interrompt le dispatch.
 */
final class Router
{
    /**
     * @var array<int, array{
     *   method:   string,
     *   regex:    string,
     *   params:   array<int, string>,
     *   handler:  array{0: class-string, 1: string},
     *   middlewares: array<int, class-string>
     * }>
     */
    private array $routes = [];

    public function get(string $path, array $handler, array $middlewares = []): void    { $this->add('GET', $path, $handler, $middlewares); }
    public function post(string $path, array $handler, array $middlewares = []): void   { $this->add('POST', $path, $handler, $middlewares); }
    public function put(string $path, array $handler, array $middlewares = []): void    { $this->add('PUT', $path, $handler, $middlewares); }
    public function patch(string $path, array $handler, array $middlewares = []): void  { $this->add('PATCH', $path, $handler, $middlewares); }
    public function delete(string $path, array $handler, array $middlewares = []): void { $this->add('DELETE', $path, $handler, $middlewares); }

    private function add(string $method, string $path, array $handler, array $middlewares): void
    {
        $params = [];
        // {name} -> [^/]+ ; {name:regex} -> regex fournie
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            function (array $m) use (&$params): string {
                $params[] = $m[1];
                $pattern  = $m[2] ?? '[^/]+';
                return '(' . $pattern . ')';
            },
            $path
        );

        $this->routes[] = [
            'method'      => $method,
            'regex'       => '#^' . $regex . '$#',
            'params'      => $params,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        // HEAD = GET sans body (RFC 7231 §4.3.2)
        $lookupMethod = ($method === 'HEAD') ? 'GET' : $method;

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $lookupMethod) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            array_shift($matches);
            if (count($route['params']) !== count($matches)) {
                throw new RuntimeException('Router params mismatch for route ' . $path);
            }
            $args = array_combine($route['params'], $matches);

            foreach ($route['middlewares'] as $mw) {
                (new $mw())->handle($args);
            }

            [$class, $action] = $route['handler'];
            (new $class())->$action($args);
            return;
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            Response::error('METHOD_NOT_ALLOWED', 'Method not allowed on this route', 405);
        }

        Response::error('NOT_FOUND', 'Route not found', 404, ['path' => $path]);
    }
}

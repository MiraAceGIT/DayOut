<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Middleware;
use LogicException;

/** Controllers et middlewares donnes par nom de classe sont instancies sans argument. */
final class Router
{
    /**
     * @var array<int, array{
     *   method:      string,
     *   regex:       string,
     *   handler:     array{0: class-string, 1: string},
     *   middlewares: array<int, Middleware|class-string<Middleware>>
     * }>
     */
    private array $routes = [];

    private string $prefix = '';

    /** @var array<int, Middleware|class-string<Middleware>> */
    private array $groupMiddlewares = [];

    public function get(string $path, array $handler, array $middlewares = []): void    { $this->add('GET', $path, $handler, $middlewares); }
    public function post(string $path, array $handler, array $middlewares = []): void   { $this->add('POST', $path, $handler, $middlewares); }
    public function put(string $path, array $handler, array $middlewares = []): void    { $this->add('PUT', $path, $handler, $middlewares); }
    public function patch(string $path, array $handler, array $middlewares = []): void  { $this->add('PATCH', $path, $handler, $middlewares); }
    public function delete(string $path, array $handler, array $middlewares = []): void { $this->add('DELETE', $path, $handler, $middlewares); }

    public function group(string $prefix, array $middlewares, callable $routes): void
    {
        [$previousPrefix, $previousMiddlewares] = [$this->prefix, $this->groupMiddlewares];
        $this->prefix           .= $prefix;
        $this->groupMiddlewares  = [...$this->groupMiddlewares, ...$middlewares];
        try {
            $routes($this);
        } finally {
            [$this->prefix, $this->groupMiddlewares] = [$previousPrefix, $previousMiddlewares];
        }
    }

    public function dispatch(Request $request): Response
    {
        // HEAD = GET sans body (RFC 7231 §4.3.2) : le serveur web retire le body.
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            // Groupes nommes uniquement : un groupe capturant dans une regex de parametre ne decale rien.
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return $this->run($route, $request->withParams($params));
        }

        if ($allowed !== []) {
            throw HttpException::methodNotAllowed(array_values(array_unique($allowed)));
        }
        throw HttpException::notFound('Route not found', ['path' => $request->path]);
    }

    private function add(string $method, string $path, array $handler, array $middlewares): void
    {
        $path = '/' . trim($this->prefix . $path, '/');
        $names = [];
        // {name} -> [^/]+ ; {name:regex} -> regex fournie
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            function (array $m) use (&$names, $path): string {
                if (in_array($m[1], $names, true)) {
                    throw new LogicException("Duplicate route parameter '{$m[1]}' in $path");
                }
                $names[] = $m[1];
                return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            },
            $path
        );

        $this->routes[] = [
            'method'      => $method,
            'regex'       => '#^' . $regex . '$#',
            'handler'     => $handler,
            'middlewares' => [...$this->groupMiddlewares, ...$middlewares],
        ];
    }

    /** Pipeline : middlewares dans l'ordre de declaration, puis l'action. */
    private function run(array $route, Request $request): Response
    {
        [$class, $action] = $route['handler'];
        $next = static function (Request $request) use ($class, $action): Response {
            $response = (new $class())->$action($request);
            if (!$response instanceof Response) {
                throw new LogicException("$class::$action must return a Response");
            }
            return $response;
        };

        foreach (array_reverse($route['middlewares']) as $middleware) {
            $middleware = is_string($middleware) ? new $middleware() : $middleware;
            if (!$middleware instanceof Middleware) {
                throw new LogicException($middleware::class . ' must implement ' . Middleware::class);
            }
            $inner = $next;
            $next = static fn (Request $request): Response => $middleware->process($request, $inner);
        }

        return $next($request);
    }
}

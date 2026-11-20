<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Middleware;
use LogicException;

/**
 * Controllers et middlewares donnes par nom de classe sont obtenus du Container (dependances injectees).
 *
 * @phpstan-type Handler array{0: class-string, 1: string}
 * @phpstan-type Middlewares array<int, Middleware|class-string<Middleware>>
 */
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

    public function __construct(private readonly Container $container = new Container())
    {
    }

    /** @param Handler $handler @param Middlewares $middlewares */
    public function get(string $path, array $handler, array $middlewares = []): void    { $this->add('GET', $path, $handler, $middlewares); }
    /** @param Handler $handler @param Middlewares $middlewares */
    public function post(string $path, array $handler, array $middlewares = []): void   { $this->add('POST', $path, $handler, $middlewares); }
    /** @param Handler $handler @param Middlewares $middlewares */
    public function put(string $path, array $handler, array $middlewares = []): void    { $this->add('PUT', $path, $handler, $middlewares); }
    /** @param Handler $handler @param Middlewares $middlewares */
    public function patch(string $path, array $handler, array $middlewares = []): void  { $this->add('PATCH', $path, $handler, $middlewares); }
    /** @param Handler $handler @param Middlewares $middlewares */
    public function delete(string $path, array $handler, array $middlewares = []): void { $this->add('DELETE', $path, $handler, $middlewares); }

    /** @param Middlewares $middlewares */
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

    /** @param Handler $handler @param Middlewares $middlewares */
    private function add(string $method, string $path, array $handler, array $middlewares): void
    {
        $path = '/' . trim($this->prefix . $path, '/');

        $this->routes[] = [
            'method'      => $method,
            'regex'       => '#^' . RouteCompiler::compile($path) . '$#',
            'handler'     => $handler,
            'middlewares' => [...$this->groupMiddlewares, ...$middlewares],
        ];
    }

    /**
     * Pipeline : middlewares dans l'ordre de declaration, puis l'action.
     *
     * @param array{handler: Handler, middlewares: Middlewares} $route
     */
    private function run(array $route, Request $request): Response
    {
        [$class, $action] = $route['handler'];
        $container = $this->container;
        $next = static function (Request $request) use ($container, $class, $action): Response {
            $response = $container->get($class)->$action($request);
            if (!$response instanceof Response) {
                throw new LogicException("$class::$action must return a Response");
            }
            return $response;
        };

        foreach (array_reverse($route['middlewares']) as $middleware) {
            $middleware = is_string($middleware) ? $this->container->get($middleware) : $middleware;
            // Les fichiers de routes ne sont pas types a l'execution : un nom de classe quelconque reste possible.
            // @phpstan-ignore instanceof.alwaysTrue
            if (!$middleware instanceof Middleware) {
                throw new LogicException($middleware::class . ' must implement ' . Middleware::class);
            }
            $inner = $next;
            $next = static fn (Request $request): Response => $middleware->process($request, $inner);
        }

        return $next($request);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\Middleware;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use LogicException;
use Tests\TestCase;

/** Controller de test : renvoie ce qu'il a recu. */
final class EchoController
{
    public function show(Request $request): Response
    {
        return Response::json([
            'id'    => $request->param('id'),
            'trace' => $request->attribute('trace', []),
        ]);
    }

    public function broken(Request $request): string
    {
        return 'not a response';
    }
}

/** Middleware de test : note son passage avant et apres l'action. */
final class TraceMiddleware implements Middleware
{
    public function __construct(private readonly string $name = 'mw')
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $trace = [...$request->attribute('trace', []), $this->name];
        return $next($request->withAttribute('trace', $trace))->withHeader("X-After-{$this->name}", 'yes');
    }
}

final class RouterTest extends TestCase
{
    private function get(Router $router, string $path, string $method = 'GET'): Response
    {
        return $router->dispatch(new Request($method, $path));
    }

    public function testNamedParametersArePassedToTheAction(): void
    {
        $router = new Router();
        $router->get('/items/{id:\d+}', [EchoController::class, 'show']);

        $this->assertSame('42', $this->get($router, '/items/42')->payload()['data']['id']);
    }

    public function testCapturingGroupInsideAParameterRegexDoesNotShiftParameters(): void
    {
        $router = new Router();
        $router->get('/items/{id:(\d+)}', [EchoController::class, 'show']);

        $this->assertSame('7', $this->get($router, '/items/7')->payload()['data']['id']);
    }

    public function testUnknownPathThrows404AndWrongMethodThrows405WithAllow(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show']);
        $router->delete('/items/{id}', [EchoController::class, 'show']);

        $e = $this->assertThrows(HttpException::class, fn () => $this->get($router, '/nope'));
        $this->assertSame(404, $e->status);
        $this->assertSame(['path' => '/nope'], $e->details);

        $e = $this->assertThrows(HttpException::class, fn () => $this->get($router, '/items/1', 'POST'));
        $this->assertSame(405, $e->status);
        $this->assertSame(['Allow' => 'GET, DELETE'], $e->headers);
    }

    public function testHeadIsRoutedLikeGet(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show']);

        $this->assertSame(200, $this->get($router, '/items/1', 'HEAD')->status);
    }

    public function testMiddlewaresRunInOrderAndCanActAfterTheAction(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show'], [new TraceMiddleware('a'), new TraceMiddleware('b')]);

        $response = $this->get($router, '/items/1');
        $this->assertSame(['a', 'b'], $response->payload()['data']['trace']);
        $this->assertSame('yes', $response->header('X-After-a'));
        $this->assertSame('yes', $response->header('X-After-b'));
    }

    public function testMiddlewareGivenAsClassNameIsInstantiated(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show'], [TraceMiddleware::class]);

        $this->assertSame(['mw'], $this->get($router, '/items/1')->payload()['data']['trace']);
    }

    public function testGroupsPrefixPathsAndStackMiddlewares(): void
    {
        $router = new Router();
        $router->group('/api', [new TraceMiddleware('api')], function (Router $r): void {
            $r->group('/items', [new TraceMiddleware('items')], function (Router $r): void {
                $r->get('/{id}', [EchoController::class, 'show'], [new TraceMiddleware('route')]);
            });
        });
        $router->get('/outside/{id}', [EchoController::class, 'show']);

        $this->assertSame(['api', 'items', 'route'], $this->get($router, '/api/items/3')->payload()['data']['trace']);
        $this->assertSame([], $this->get($router, '/outside/3')->payload()['data']['trace'], 'group state restored');
    }

    public function testMiddlewareCanRejectTheRequest(): void
    {
        $deny = new class implements Middleware {
            public function process(Request $request, callable $next): Response
            {
                throw new HttpException(401, 'UNAUTHENTICATED', 'Login required');
            }
        };
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show'], [$deny]);

        $e = $this->assertThrows(HttpException::class, fn () => $this->get($router, '/items/1'));
        $this->assertSame(401, $e->status);
    }

    public function testActionMustReturnAResponse(): void
    {
        $router = new Router();
        $router->get('/broken', [EchoController::class, 'broken']);

        $this->assertThrows(LogicException::class, fn () => $this->get($router, '/broken'));
    }

    public function testDuplicateParameterNameIsRejected(): void
    {
        $this->assertThrows(LogicException::class, fn () => (new Router())->get('/{id}/{id}', [EchoController::class, 'show']));
    }
}

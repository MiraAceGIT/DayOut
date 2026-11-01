<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use App\Core\Http\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use LogicException;
use Tests\Support\EchoController;
use Tests\TestCase;

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

        $this->assertSame('42', self::payload($this->get($router, '/items/42'))['data']['id']);
    }

    public function testParameterRegexMayContainBraces(): void
    {
        $router = new Router();
        $router->get('/lang/{id:[a-z]{2}}', [EchoController::class, 'show']);

        $this->assertSame('fr', self::payload($this->get($router, '/lang/fr'))['data']['id']);
        $this->assertThrows(HttpException::class, fn () => $this->get($router, '/lang/fra'));
    }

    public function testStaticPartsAreMatchedLiterally(): void
    {
        $router = new Router();
        $router->get('/v1.0/{id}', [EchoController::class, 'show']);

        $this->assertSame('5', self::payload($this->get($router, '/v1.0/5'))['data']['id']);
        $this->assertThrows(HttpException::class, fn () => $this->get($router, '/v1x0/5'), 'dot is not a wildcard');
    }

    public function testMalformedParameterIsRejected(): void
    {
        foreach (['/{id', '/{1id}', '/{id:}'] as $path) {
            $this->assertThrows(LogicException::class, fn () => (new Router())->get($path, [EchoController::class, 'show']), $path);
        }
    }

    public function testCapturingGroupInsideAParameterRegexDoesNotShiftParameters(): void
    {
        $router = new Router();
        $router->get('/items/{id:(\d+)}', [EchoController::class, 'show']);

        $this->assertSame('7', self::payload($this->get($router, '/items/7'))['data']['id']);
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
        $this->assertSame(['a', 'b'], self::payload($response)['data']['trace']);
        $this->assertSame('yes', $response->header('X-After-a'));
        $this->assertSame('yes', $response->header('X-After-b'));
    }

    public function testMiddlewareGivenAsClassNameIsInstantiated(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show'], [TraceMiddleware::class]);

        $this->assertSame(['mw'], self::payload($this->get($router, '/items/1'))['data']['trace']);
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

        $this->assertSame(['api', 'items', 'route'], self::payload($this->get($router, '/api/items/3'))['data']['trace']);
        $this->assertSame([], self::payload($this->get($router, '/outside/3'))['data']['trace'], 'group state restored');
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

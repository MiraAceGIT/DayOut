<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use App\Core\Config;
use App\Core\Http\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use RuntimeException;
use Tests\Support\EchoController;
use Tests\TestCase;

final class FailingController
{
    public function fail(Request $request): Response
    {
        throw new RuntimeException('secret internal detail');
    }
}

final class KernelTest extends TestCase
{
    private const ORIGIN = 'http://front.test';

    private function kernel(): Kernel
    {
        $router = new Router();
        $router->get('/items/{id}', [EchoController::class, 'show']);
        $router->get('/fail', [FailingController::class, 'fail']);
        return new Kernel($router, new Config(corsOrigins: [self::ORIGIN]));
    }

    public function testErrorsAreRenderedAsJsonEnvelope(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/nope'));

        $this->assertSame(404, $response->status);
        $this->assertSame('NOT_FOUND', self::payload($response)['error']['code']);
    }

    public function testUnexpectedExceptionIsHiddenBehindGeneric500(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/fail'));

        $this->assertSame(500, $response->status);
        $this->assertSame(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Something went wrong']], $response->payload());
    }

    public function testSecurityHeadersAndRequestIdOnEveryResponseIncludingErrors(): void
    {
        foreach (['/items/1', '/nope', '/fail'] as $path) {
            $response = $this->kernel()->handle(new Request('GET', $path));
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'), $path);
            $this->assertStringContains("default-src 'none'", (string) $response->header('Content-Security-Policy'), $path);
            $this->assertTrue(preg_match('/^[0-9a-f]{16}$/', (string) $response->header('X-Request-Id')) === 1, $path);
        }
    }

    public function testCorsHeadersOnlyForAllowedOriginAndAlsoOnErrors(): void
    {
        $allowed = $this->kernel()->handle(new Request('GET', '/fail', headers: ['origin' => self::ORIGIN]));
        $this->assertSame(self::ORIGIN, $allowed->header('Access-Control-Allow-Origin'));
        $this->assertSame('X-Request-Id', $allowed->header('Access-Control-Expose-Headers'));

        $other = $this->kernel()->handle(new Request('GET', '/items/1', headers: ['origin' => 'http://evil.test']));
        $this->assertSame(null, $other->header('Access-Control-Allow-Origin'));
    }

    public function testPreflightIsAnsweredWithoutRouting(): void
    {
        $response = $this->kernel()->handle(new Request('OPTIONS', '/anything', headers: ['origin' => self::ORIGIN]));

        $this->assertSame(204, $response->status);
        $this->assertSame(null, $response->payload());
        $this->assertSame(self::ORIGIN, $response->header('Access-Control-Allow-Origin'));
    }
}

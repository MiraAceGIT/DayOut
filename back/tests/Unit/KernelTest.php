<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Http\ExceptionRenderer;
use App\Core\Http\Kernel;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PDOException;
use RuntimeException;
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
        $this->assertSame('NOT_FOUND', $response->payload()['error']['code']);
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

    public function testRendererCopiesHttpExceptionHeaders(): void
    {
        $response = ExceptionRenderer::render(HttpException::methodNotAllowed(['GET', 'POST']));

        $this->assertSame(405, $response->status);
        $this->assertSame('GET, POST', $response->header('Allow'));
    }

    private static function pdoError(string $state, int $errno, string $message): PDOException
    {
        $e = new PDOException("SQLSTATE[$state]: $errno $message");
        $e->errorInfo = [$state, $errno, $message];
        return $e;
    }

    public function testTriggerBusinessRuleBecomesClientError(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('45000', 50304, 'Event is full'));
        $this->assertSame(409, $response->status);
        $this->assertSame(['code' => 'EVENT_FULL', 'message' => 'Event is full'], $response->payload()['error']);

        $this->assertSame(403, ExceptionRenderer::render(self::pdoError('45000', 50301, 'User is banned'))->status);
        $unknown = ExceptionRenderer::render(self::pdoError('45000', 50999, 'Some new rule'));
        $this->assertSame('BUSINESS_RULE_VIOLATION', $unknown->payload()['error']['code']);
    }

    public function testNativeConstraintErrorsGetGenericMessages(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('23000', 1062, "Duplicate entry '1-3' for key 'uk_reg_event_user'"));

        $this->assertSame(409, $response->status);
        $this->assertSame(['code' => 'CONFLICT', 'message' => 'Resource already exists'], $response->payload()['error']);
    }

    public function testOtherDatabaseErrorsStayInternal(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('42S22', 1054, "Unknown column 'x'"));
        $this->assertSame(500, $response->status);
        $this->assertSame('INTERNAL_ERROR', $response->payload()['error']['code']);
    }
}

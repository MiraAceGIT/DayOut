<?php
declare(strict_types=1);

namespace Tests\Http;

use Tests\HttpTestCase;

final class RoutingTest extends HttpTestCase
{
    public function testUnknownRouteReturns404WithPath(): void
    {
        $error = $this->assertError(404, 'NOT_FOUND', $this->get('/api/nope'));
        $this->assertSame(['path' => '/api/nope'], $error['details']);
    }

    public function testNonNumericIdDoesNotMatchTheRoute(): void
    {
        $error = $this->assertError(404, 'NOT_FOUND', $this->get('/api/events/abc'));
        $this->assertSame('Route not found', $error['message']);
    }

    public function testTrailingSlashIsIgnored(): void
    {
        $this->assertStatus(200, $this->get('/api/events/'));
    }

    public function testWrongMethodReturns405WithAllowHeader(): void
    {
        $response = $this->post('/api/events', ['title' => 'x']);

        $this->assertError(405, 'METHOD_NOT_ALLOWED', $response);
        $this->assertSame('GET', $response->header('Allow'));
    }

    public function testPreflightFromAllowedOriginGetsCorsHeaders(): void
    {
        $response = $this->request('OPTIONS', '/api/events', null, ['Origin' => self::ALLOWED_ORIGIN]);

        $this->assertStatus(204, $response);
        $this->assertSame(self::ALLOWED_ORIGIN, $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->header('Access-Control-Allow-Credentials'));
        $this->assertStringContains('X-Request-Id', (string) $response->header('Access-Control-Expose-Headers'));
    }

    public function testOtherOriginsGetNoCorsHeaders(): void
    {
        $response = $this->get('/api/events', ['Origin' => 'http://evil.test']);

        $this->assertStatus(200, $response);
        $this->assertSame(null, $response->header('Access-Control-Allow-Origin'));
    }
}

<?php
declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\HttpTestCase;

final class RouterTest extends HttpTestCase
{
    public function testUnknownRouteReturns404WithPath(): void
    {
        $error = $this->assertError(404, 'NOT_FOUND', $this->get('/nope'));
        $this->assertSame(['path' => '/nope'], $error['details']);
    }

    public function testNonNumericIdDoesNotMatchTheRoute(): void
    {
        $error = $this->assertError(404, 'NOT_FOUND', $this->get('/events/abc'));
        $this->assertSame('Route not found', $error['message']);
    }

    public function testTrailingSlashIsIgnored(): void
    {
        $this->assertStatus(200, $this->get('/events/'));
    }

    public function testWrongMethodReturns405WithAllowHeader(): void
    {
        $response = $this->request('PUT', '/events', ['title' => 'x']);

        $this->assertError(405, 'METHOD_NOT_ALLOWED', $response);
        $this->assertSame('GET, POST', $response->header('Allow'));
    }
}

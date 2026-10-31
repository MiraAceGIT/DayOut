<?php
declare(strict_types=1);

namespace Tests\Integration\Core\Http;

use Tests\HttpTestCase;

final class CorsTest extends HttpTestCase
{
    public function testPreflightFromAllowedOriginGetsCorsHeaders(): void
    {
        $response = $this->request('OPTIONS', '/events', null, ['Origin' => self::ALLOWED_ORIGIN]);

        $this->assertStatus(204, $response);
        $this->assertSame(self::ALLOWED_ORIGIN, $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->header('Access-Control-Allow-Credentials'));
        $this->assertStringContains('X-Request-Id', (string) $response->header('Access-Control-Expose-Headers'));
    }

    public function testOtherOriginsGetNoCorsHeaders(): void
    {
        $response = $this->get('/events', ['Origin' => 'http://evil.test']);

        $this->assertStatus(200, $response);
        $this->assertSame(null, $response->header('Access-Control-Allow-Origin'));
    }
}

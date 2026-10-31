<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\HttpTestCase;

final class HealthTest extends HttpTestCase
{
    public function testApiAndDatabaseAreUp(): void
    {
        $response = $this->get('/health');

        $this->assertStatus(200, $response);
        $data = $response->json()['data'];
        $this->assertSame('up', $data['api']);
        $this->assertSame('up', $data['db']);
        $this->assertArrayHasKey('time', $data);
    }

    public function testEveryResponseCarriesRequestIdAndSecurityHeaders(): void
    {
        foreach (['/health', '/does-not-exist'] as $path) {
            $response = $this->get($path);
            $this->assertTrue(preg_match('/^[0-9a-f]{16}$/', (string) $response->header('X-Request-Id')) === 1, "X-Request-Id on $path");
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
            $this->assertSame('DENY', $response->header('X-Frame-Options'));
            $this->assertStringContains("default-src 'none'", (string) $response->header('Content-Security-Policy'));
            $this->assertStringContains('application/json', (string) $response->header('Content-Type'));
        }
    }
}

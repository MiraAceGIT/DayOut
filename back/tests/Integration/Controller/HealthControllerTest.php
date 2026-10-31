<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

final class HealthControllerTest extends HttpTestCase
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
}

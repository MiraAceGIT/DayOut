<?php
declare(strict_types=1);

namespace Tests\Integration\Core\Log;

use Tests\HttpTestCase;

final class AccessLogTest extends HttpTestCase
{
    public function testEachRequestWritesOneAccessLineWithItsRequestId(): void
    {
        $response  = $this->get('/events/99999?token=should-not-be-logged');
        $requestId = (string) $response->header('X-Request-Id');

        $entries = array_values(array_filter(
            $this->readLog('info'),
            fn (array $entry): bool => $entry['requestId'] === $requestId && $entry['message'] === 'HTTP request'
        ));

        $this->assertCount(1, $entries);
        $this->assertSame('GET', $entries[0]['method']);
        $this->assertSame('/events/99999', $entries[0]['path'], 'query string stripped');
        $this->assertSame(404, $entries[0]['context']['status']);
    }
}

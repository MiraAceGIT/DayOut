<?php
declare(strict_types=1);

namespace Tests\Integration\Core\Http;

use Tests\HttpTestCase;

final class ExceptionRendererTest extends HttpTestCase
{
    public function testBusinessExceptionIsLoggedWithItsRequestId(): void
    {
        $requestId = (string) $this->get('/events/99999')->header('X-Request-Id');

        $entries = array_values(array_filter(
            $this->readLog('info'),
            fn (array $entry): bool => $entry['requestId'] === $requestId && $entry['message'] === 'Business rule violated'
        ));

        $this->assertCount(1, $entries);
        $this->assertSame('EVENT_NOT_FOUND', $entries[0]['context']['code']);
        $this->assertSame(99999, $entries[0]['context']['id']);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Http;

use Tests\HttpTestCase;

final class LoggingTest extends HttpTestCase
{
    public function testEachRequestWritesOneAccessLineWithItsRequestId(): void
    {
        $response  = $this->get('/api/events/99999?token=should-not-be-logged');
        $requestId = (string) $response->header('X-Request-Id');

        $entries = array_values(array_filter(
            $this->readLog('info'),
            fn (array $entry): bool => $entry['requestId'] === $requestId
        ));

        $this->assertCount(1, $entries);
        $this->assertSame('HTTP request', $entries[0]['message']);
        $this->assertSame('GET', $entries[0]['method']);
        $this->assertSame('/api/events/99999', $entries[0]['path'], 'query string stripped');
        $this->assertSame(404, $entries[0]['context']['status']);
    }

    /** @return array<int, array<string, mixed>> */
    private function readLog(string $channel): array
    {
        $files = glob(getenv('TEST_SERVER_LOG_PATH') . "/$channel-*.log") ?: [];
        $this->assertCount(1, $files, "$channel log file");

        $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_map(fn (string $line): array => json_decode($line, true), $lines);
    }
}

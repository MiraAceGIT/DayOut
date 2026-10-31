<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\HttpTestCase;

final class LoggingTest extends HttpTestCase
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

    /** @return array<int, array<string, mixed>> */
    private function readLog(string $channel): array
    {
        // Plusieurs fichiers si la suite traverse minuit UTC (un fichier par jour).
        $files = glob(getenv('TEST_SERVER_LOG_PATH') . "/$channel-*.log") ?: [];
        $this->assertTrue($files !== [], "$channel log file");

        $lines = array_merge(...array_map(
            fn (string $file): array => file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
            $files
        ));
        return array_map(fn (string $line): array => json_decode($line, true), $lines);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

/** ErrorHandler::handleException() termine par exit : chaque cas tourne dans un processus PHP a part. */
final class ErrorHandlerTest extends TestCase
{
    private string $logDir;

    public function setUp(): void
    {
        $this->logDir = getenv('TEST_TMP_DIR') . '/error-handler-' . bin2hex(random_bytes(4));
    }

    public function testHttpExceptionIsReturnedToClientWithoutErrorLog(): void
    {
        $output = $this->runFixture('http');

        $this->assertSame(
            ['error' => ['code' => 'CONFLICT', 'message' => 'Already registered', 'details' => ['eventId' => 3]]],
            json_decode($output, true)
        );
        $this->assertSame([], glob($this->logDir . '/error-*.log') ?: []);
    }

    public function testOtherExceptionBecomesGeneric500AndIsLogged(): void
    {
        $output = $this->runFixture('pdo');

        $this->assertSame(
            ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Something went wrong']],
            json_decode($output, true)
        );
        $this->assertStringNotContains('secret_col', $output);

        $logs = glob($this->logDir . '/error-*.log') ?: [];
        $this->assertCount(1, $logs);
        $entry = json_decode((string) file_get_contents($logs[0]), true);
        $this->assertSame('Unhandled exception', $entry['message']);
        $this->assertSame('PDOException', $entry['context']['exception']['class']);
        $this->assertStringContains('secret_col', $entry['context']['exception']['message']);
    }

    private function runFixture(string $case): string
    {
        $fixture = dirname(__DIR__) . '/fixtures/throw.php';
        $env     = ['LOG_PATH' => $this->logDir, 'APP_DEBUG' => 'false'] + getenv();
        $process = proc_open([PHP_BINARY, $fixture, $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot run fixture');
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $this->assertSame('', $stderr, 'stderr of the fixture');
        return $stdout;
    }
}

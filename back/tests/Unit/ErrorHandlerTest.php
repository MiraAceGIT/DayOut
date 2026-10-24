<?php
declare(strict_types=1);

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

/** Filets de securite hors Kernel : chaque cas tourne dans un processus PHP a part. */
final class ErrorHandlerTest extends TestCase
{
    private const INTERNAL_ERROR = ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Something went wrong']];

    private string $logDir;

    public function setUp(): void
    {
        $this->logDir = getenv('TEST_TMP_DIR') . '/error-handler-' . bin2hex(random_bytes(4));
    }

    public function testUncaughtExceptionBecomesGeneric500AndIsLogged(): void
    {
        $output = $this->runFixture('uncaught');

        $this->assertSame(self::INTERNAL_ERROR, json_decode($output, true));
        $this->assertStringNotContains('secret_detail', $output);

        $entry = $this->lastErrorEntry();
        $this->assertSame('critical', $entry['level']);
        $this->assertSame('Uncaught exception', $entry['message']);
        $this->assertStringContains('secret_detail', $entry['context']['exception']['message']);
    }

    public function testFatalErrorStillReturnsJsonEnvelope(): void
    {
        $output = $this->runFixture('fatal');

        $this->assertSame(self::INTERNAL_ERROR, json_decode($output, true), 'partial output discarded');
        $entry = $this->lastErrorEntry();
        $this->assertSame('Fatal error', $entry['message']);
        $this->assertStringContains('Maximum execution time', $entry['context']['message']);
    }

    private function runFixture(string $case): string
    {
        $fixture = dirname(__DIR__) . '/fixtures/throw.php';
        $env     = ['LOG_PATH' => $this->logDir] + getenv();
        // output_buffering : comme sous un serveur web, la sortie partielle reste jetable.
        // log_errors=0 : sinon PHP CLI recopie l'erreur fatale sur stderr.
        $cmd     = [PHP_BINARY, '-d', 'output_buffering=4096', '-d', 'log_errors=0', $fixture, $case];
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
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

    /** @return array<string, mixed> */
    private function lastErrorEntry(): array
    {
        $files = glob($this->logDir . '/error-*.log') ?: [];
        $this->assertTrue($files !== [], 'error log written');
        $lines = file((string) end($files), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return json_decode((string) end($lines), true);
    }
}

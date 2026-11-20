<?php
declare(strict_types=1);

namespace Tests;

use RuntimeException;

/** Serveur PHP integre (php -S) lance en arriere-plan sur un port libre, le temps de la suite. */
final class TestServer
{
    /** @var resource */
    private $process;

    /** @param resource $process */
    private function __construct($process, public readonly string $baseUrl)
    {
        $this->process = $process;
    }

    /** @param array<string, string> $env variables ajoutees a l'environnement courant */
    public static function start(string $docRoot, array $env, string $outputFile): self
    {
        $port = self::freePort();
        $cmd  = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $docRoot, __DIR__ . '/Support/router.php'];
        $out  = ['file', $outputFile, 'a'];

        // Commande en tableau : pas de shell, proc_terminate() arrete bien php.exe sous Windows.
        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => $out, 2 => $out], $pipes, null, $env + getenv());
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the test server');
        }
        $server = new self($process, "http://127.0.0.1:$port");
        $server->waitUntilReady($port, $outputFile);
        return $server;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
    }

    private function waitUntilReady(int $port, string $outputFile): void
    {
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            if (!proc_get_status($this->process)['running']) {
                break;
            }
            usleep(100_000);
        }
        $this->stop();
        throw new RuntimeException("Test server did not start on port $port:\n" . @file_get_contents($outputFile));
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("Cannot find a free port: $errstr");
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}

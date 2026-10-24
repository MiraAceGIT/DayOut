<?php
declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * Tests d'integration : vraies requetes HTTP sur le serveur demarre par
 * tests/run.php, branche sur la base de test remise a zero (seed.sql).
 */
abstract class HttpTestCase extends TestCase
{
    /** Origine autorisee par le serveur de test (CORS_ORIGIN). */
    public const ALLOWED_ORIGIN = 'http://front.test';

    protected function get(string $path, array $headers = []): TestResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    protected function post(string $path, mixed $json = null, array $headers = []): TestResponse
    {
        return $this->request('POST', $path, $json, $headers);
    }

    /** @param array<string, string> $headers */
    protected function request(string $method, string $path, mixed $json = null, array $headers = [], ?string $rawBody = null): TestResponse
    {
        $body = $rawBody ?? ($json === null ? null : json_encode($json, JSON_THROW_ON_ERROR));
        if ($body !== null) {
            $headers += ['Content-Type' => 'application/json'];
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "$name: $value";
        }

        $context = stream_context_create(['http' => [
            'method'          => $method,
            'header'          => implode("\r\n", $lines),
            'content'         => $body ?? '',
            'ignore_errors'   => true, // lire aussi le body des 4xx / 5xx
            'follow_location' => 0,
            'timeout'         => 10,
        ]]);

        $content = @file_get_contents(self::baseUrl() . $path, false, $context);
        $raw     = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? null);
        if ($content === false || !is_array($raw) || $raw === []) {
            throw new RuntimeException("No response for $method $path (test server down?)");
        }
        return self::parse($raw, $content);
    }

    protected function assertStatus(int $expected, TestResponse $response): void
    {
        $this->assertSame($expected, $response->status, 'HTTP status, body: ' . substr($response->body, 0, 300));
    }

    /** Verifie l'enveloppe { error: { code, message } } ; renvoie error pour d'autres assertions. */
    protected function assertError(int $status, string $code, TestResponse $response): array
    {
        $this->assertStatus($status, $response);
        $json = $response->json();
        $this->assertArrayHasKey('error', $json);
        $this->assertSame($code, $json['error']['code'] ?? null);
        return $json['error'];
    }

    private static function baseUrl(): string
    {
        $url = getenv('TEST_BASE_URL');
        if ($url === false || $url === '') {
            throw new RuntimeException('TEST_BASE_URL is not set: run the suite with tests/run.sh');
        }
        return $url;
    }

    /** @param array<int, string> $raw lignes d'en-tete, statut en premier */
    private static function parse(array $raw, string $body): TestResponse
    {
        $status  = 0;
        $headers = [];
        foreach ($raw as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status  = (int) $m[1];
                $headers = []; // reponse finale uniquement (ex. apres un 100 Continue)
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }
        return new TestResponse($status, $headers, $body);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Log\ContextSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Tests\TestCase;

final class ContextSanitizerTest extends TestCase
{
    public function testSensitiveKeysAreRedactedAtAnyDepth(): void
    {
        $clean = ContextSanitizer::sanitize([
            'email'   => 'yovanne@isep.fr',
            'password' => 'Password!2026',
            'user'    => ['api_key' => 'abc', 'name' => 'Yovanne'],
            'headers' => ['Authorization' => 'Bearer x', 'X-CSRF-Token' => 'y'],
        ]);
        $this->assertSame('yovanne@isep.fr', $clean['email']);
        $this->assertSame(ContextSanitizer::MASK, $clean['password']);
        $this->assertSame(ContextSanitizer::MASK, $clean['user']['api_key']);
        $this->assertSame('Yovanne', $clean['user']['name']);
        $this->assertSame(ContextSanitizer::MASK, $clean['headers']['Authorization']);
        $this->assertSame(ContextSanitizer::MASK, $clean['headers']['X-CSRF-Token']);
    }

    public function testExceptionIsSerializedWithPreviousAndArgumentFreeTrace(): void
    {
        $e = $this->throwWithSecretArgument('s3cr3t-value');
        $clean = ContextSanitizer::sanitize(['exception' => $e]);

        $exception = $clean['exception'];
        $this->assertSame(RuntimeException::class, $exception['class']);
        $this->assertSame('outer', $exception['message']);
        $this->assertSame('inner', $exception['previous']['message']);
        $this->assertStringNotContains('s3cr3t-value', (string) json_encode($clean));
    }

    public function testObjectsAreReducedToSerializableValues(): void
    {
        $clean = ContextSanitizer::sanitize([
            'at'  => new DateTimeImmutable('2026-10-24 09:00:00', new DateTimeZone('UTC')),
            'obj' => new \stdClass(),
        ]);
        $this->assertSame('2026-10-24T09:00:00+00:00', $clean['at']);
        $this->assertSame('stdClass', $clean['obj']);
    }

    public function testKnownSecretsAreScrubbedFromFreeText(): void
    {
        $line = ContextSanitizer::scrubSecrets('{"message":"Access denied using password hunter2-db"}', ['hunter2-db']);
        $this->assertStringNotContains('hunter2-db', $line);
        $this->assertStringContains(ContextSanitizer::MASK, $line);
    }

    public function testSecretsAreMatchedInTheirJsonEncodedForm(): void
    {
        // Memes options d'encodage que Log.
        $line = (string) json_encode(['message' => 'pwd is a"b/c'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContains('a\"b', ContextSanitizer::scrubSecrets($line, ['a"b/c']));
    }

    public function testShortOrEmptySecretsAreIgnored(): void
    {
        $line = '{"message":"abc"}';
        $this->assertSame($line, ContextSanitizer::scrubSecrets($line, ['', 'abc']));
    }

    private function throwWithSecretArgument(string $secret): RuntimeException
    {
        try {
            $this->explode($secret);
        } catch (RuntimeException $e) {
            return new RuntimeException('outer', 0, $e);
        }
        $this->fail('explode() should have thrown'); // @phpstan-ignore deadCode.unreachable (filet si explode() changeait)
    }

    private function explode(string $secret): never
    {
        throw new RuntimeException('inner');
    }
}

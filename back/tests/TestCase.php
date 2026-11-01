<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Response;
use Throwable;

/** Chaque methode publique `test*` tourne sur une instance neuve ; setUp() avant chacune. */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    /** Toujours appele apres le test, meme en echec (remise en etat : variables d'env...). */
    public function tearDown(): void
    {
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assert($expected === $actual, $message, 'Expected ' . self::export($expected) . ', got ' . self::export($actual));
    }

    protected function assertNotSame(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        $this->assert($unexpected !== $actual, $message, 'Did not expect ' . self::export($actual));
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assert($condition, $message, 'Expected true, got false');
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assert(!$condition, $message, 'Expected false, got true');
    }

    protected function assertCount(int $expected, array $actual, string $message = ''): void
    {
        $this->assert(count($actual) === $expected, $message, "Expected $expected items, got " . count($actual));
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assert(array_key_exists($key, $array), $message, 'Missing key ' . self::export($key) . ' in ' . self::export($array));
    }

    protected function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assert(!array_key_exists($key, $array), $message, 'Unexpected key ' . self::export($key));
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(str_contains($haystack, $needle), $message, self::export($needle) . ' not found in ' . self::export($haystack));
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(!str_contains($haystack, $needle), $message, self::export($needle) . ' unexpectedly found in ' . self::export($haystack));
    }

    /** Corps JSON d'une Response (enveloppe data / error) ; echec si la reponse n'en a pas (204, raw). */
    protected static function payload(Response $response): array
    {
        return $response->payload() ?? throw new AssertionFailed('Response has no JSON payload');
    }

    /**
     * @template T of Throwable
     * @param class-string<T> $class
     * @return T l'exception levee, pour des assertions supplementaires
     */
    protected function assertThrows(string $class, callable $fn, string $message = ''): Throwable
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if (!$e instanceof $class) {
                $this->fail(($message !== '' ? "$message: " : '') . "Expected $class, got " . $e::class . ': ' . $e->getMessage());
            }
            $this->assertions++;
            return $e;
        }
        $this->fail($message !== '' ? $message : "Expected $class to be thrown");
    }

    protected function fail(string $message): never
    {
        throw new AssertionFailed($message);
    }

    private function assert(bool $condition, string $message, string $default): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->fail($message !== '' ? "$message\n$default" : $default);
        }
    }

    private static function export(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $text = $json === false ? get_debug_type($value) : $json;
        return strlen($text) > 300 ? substr($text, 0, 300) . '...' : $text;
    }
}

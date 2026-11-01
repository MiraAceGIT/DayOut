<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use RuntimeException;
use Tests\TestCase;

/** fromEnv() lit getenv() : chaque test pose ses variables, tearDown() remet l'environnement d'origine. */
final class ConfigTest extends TestCase
{
    private const KEYS = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'APP_DEBUG', 'APP_TIMEZONE', 'DOCS_ENABLED'];

    /** @var array<string, string|false> */
    private array $saved = [];

    public function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->saved[$key] = getenv($key);
        }
        self::env([
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_NAME' => 'dayout', 'DB_USER' => 'root',
            'APP_DEBUG' => null, 'APP_TIMEZONE' => null, 'DOCS_ENABLED' => null,
        ]);
    }

    public function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            putenv($value === false ? $key : "$key=$value");
        }
    }

    /** @param array<string, ?string> $vars null = variable retiree */
    private static function env(array $vars): void
    {
        foreach ($vars as $key => $value) {
            putenv($value === null ? $key : "$key=$value");
        }
    }

    public function testDefaultsAreSafeForProduction(): void
    {
        $config = Config::fromEnv('/app');

        $this->assertSame(false, $config->debug);
        $this->assertSame(false, $config->docsEnabled, 'docs off unless DOCS_ENABLED');
        $this->assertSame('UTC', $config->timezone);
    }

    public function testBooleansAcceptCommonSpellings(): void
    {
        foreach (['true' => true, '1' => true, 'yes' => true, 'false' => false, '0' => false, 'off' => false] as $raw => $expected) {
            self::env(['APP_DEBUG' => (string) $raw, 'DOCS_ENABLED' => (string) $raw]);
            $config = Config::fromEnv('/app');
            $this->assertSame($expected, $config->debug, "APP_DEBUG=$raw");
            $this->assertSame($expected, $config->docsEnabled, "DOCS_ENABLED=$raw");
        }
    }

    public function testInvalidValuesAreRejectedAtStartup(): void
    {
        self::env(['APP_DEBUG' => 'maybe']);
        $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'), 'APP_DEBUG');

        self::env(['APP_DEBUG' => null, 'APP_TIMEZONE' => 'Mars/Olympus']);
        $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'), 'APP_TIMEZONE');

        self::env(['APP_TIMEZONE' => 'Europe/Paris']);
        $this->assertSame('Europe/Paris', Config::fromEnv('/app')->timezone);
    }
}

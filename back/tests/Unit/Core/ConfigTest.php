<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use RuntimeException;
use Tests\TestCase;

/** fromEnv() lit getenv() : chaque test pose ses variables, tearDown() remet l'environnement d'origine. */
final class ConfigTest extends TestCase
{
    private const KEYS = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'APP_DEBUG', 'APP_TIMEZONE', 'DOCS_ENABLED', 'APP_ENV', 'MAIL_DEMO', 'FRONT_URL', 'TRUSTED_PROXIES', 'RATE_LIMIT_REGISTRATIONS_PER_HOUR', 'RATE_LIMIT_SECRET'];

    private const PROD_SECRET = '0123456789abcdef0123456789abcdef';

    /** @var array<string, string|false> */
    private array $saved = [];

    public function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->saved[$key] = getenv($key);
        }
        self::env([
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_NAME' => 'dayout', 'DB_USER' => 'root',
            'APP_DEBUG' => null, 'APP_TIMEZONE' => null, 'DOCS_ENABLED' => null, 'APP_ENV' => null, 'MAIL_DEMO' => null, 'FRONT_URL' => null,
            'TRUSTED_PROXIES' => null, 'RATE_LIMIT_REGISTRATIONS_PER_HOUR' => null, 'RATE_LIMIT_SECRET' => null,
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

    public function testDemoLinksAreForbiddenInProduction(): void
    {
        $dev = Config::fromEnv('/app');
        $this->assertSame([Config::ENV_DEV, false, 'http://localhost:5500'], [$dev->appEnv, $dev->isProduction(), $dev->frontUrl]);

        self::env(['APP_ENV' => 'prod', 'FRONT_URL' => 'https://app.dayout.example/', 'RATE_LIMIT_SECRET' => self::PROD_SECRET]);
        $prod = Config::fromEnv('/app');
        $this->assertTrue($prod->isProduction());
        $this->assertSame('https://app.dayout.example', $prod->frontUrl, 'no trailing slash');

        self::env(['MAIL_DEMO' => 'true']);
        $this->assertStringContains('MAIL_DEMO', $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'))->getMessage());
        self::env(['APP_ENV' => 'staging', 'MAIL_DEMO' => null]);
        $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'));
        self::env(['APP_ENV' => null, 'FRONT_URL' => 'javascript:alert(1)']);
        $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'));
    }

    public function testTrustedProxiesAndRateLimitsAreReadAndChecked(): void
    {
        $default = Config::fromEnv('/app');
        $this->assertSame([[], 5, 30, 30], [$default->trustedProxies, $default->rateLimitRegistrations,
            $default->rateLimitRegistrationAttempts, $default->rateLimitEmailVerifications], 'no proxy trusted by default');

        self::env(['TRUSTED_PROXIES' => ' 127.0.0.1, 10.0.0.0/8 ,2001:db8::/32', 'RATE_LIMIT_REGISTRATIONS_PER_HOUR' => '50']);
        $config = Config::fromEnv('/app');
        $this->assertSame(['127.0.0.1', '10.0.0.0/8', '2001:db8::/32'], $config->trustedProxies);
        $this->assertSame(50, $config->rateLimitRegistrations);

        foreach (['10.0.0.0/33', 'proxy.local', '10.0.0.0/'] as $invalid) {
            self::env(['TRUSTED_PROXIES' => $invalid]);
            $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'), "TRUSTED_PROXIES=$invalid");
        }
        self::env(['TRUSTED_PROXIES' => null, 'RATE_LIMIT_REGISTRATIONS_PER_HOUR' => '0']);
        $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'));
    }

    public function testProductionNeedsARealRateLimitSecret(): void
    {
        $this->assertSame(Config::DEV_RATE_LIMIT_SECRET, Config::fromEnv('/app')->rateLimitSecret, 'dev works out of the box');

        foreach ([null, 'trop-court', Config::DEV_RATE_LIMIT_SECRET] as $secret) {
            self::env(['APP_ENV' => 'prod', 'RATE_LIMIT_SECRET' => $secret]);
            $message = $this->assertThrows(RuntimeException::class, fn () => Config::fromEnv('/app'), (string) $secret)->getMessage();
            $this->assertStringContains('RATE_LIMIT_SECRET', $message);
        }
        self::env(['RATE_LIMIT_SECRET' => self::PROD_SECRET]);
        $this->assertSame(self::PROD_SECRET, Config::fromEnv('/app')->rateLimitSecret);
    }
}

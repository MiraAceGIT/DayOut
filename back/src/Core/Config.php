<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Log\Level;
use DateTimeZone;
use RuntimeException;

/** Seul endroit du code qui lit getenv() : les autres classes recoivent leurs valeurs. */
final class Config
{
    /** @param array<int, string> $corsOrigins */
    public function __construct(
        public readonly bool $debug = false,
        public readonly string $timezone = 'UTC',
        public readonly string $dbHost = '127.0.0.1',
        public readonly int $dbPort = 3306,
        public readonly string $dbName = 'dayout',
        public readonly string $dbUser = 'root',
        public readonly string $dbPassword = '',
        public readonly array $corsOrigins = [],
        public readonly Level $logLevel = Level::Info,
        public readonly string $logPath = '',
        public readonly int $logRetentionDays = 30,
        public readonly int $auditRetentionDays = 365,
        public readonly bool $docsEnabled = false,
    ) {
    }

    /** Variables BDD obligatoires : pas de repli silencieux sur root sans mot de passe en prod. */
    public static function fromEnv(string $appRoot): self
    {
        $missing = array_filter(
            ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'],
            static fn (string $key): bool => self::env($key) === null
        );
        if ($missing !== []) {
            throw new RuntimeException('Missing environment variable(s): ' . implode(', ', $missing));
        }

        $logLevel = self::env('LOG_LEVEL') ?? 'info';
        $timezone = self::env('APP_TIMEZONE') ?? 'UTC';
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException("Invalid APP_TIMEZONE: $timezone");
        }

        return new self(
            debug: self::bool('APP_DEBUG', false),
            timezone: $timezone,
            dbHost: (string) self::env('DB_HOST'),
            dbPort: self::positiveInt('DB_PORT', 3306),
            dbName: (string) self::env('DB_NAME'),
            dbUser: (string) self::env('DB_USER'),
            dbPassword: (string) getenv('DB_PASSWORD'), // peut etre vide en XAMPP local
            corsOrigins: array_values(array_filter(array_map('trim', explode(',', self::env('CORS_ORIGIN') ?? '')))),
            logLevel: Level::tryFrom(strtolower($logLevel))
                ?? throw new RuntimeException("Invalid LOG_LEVEL: $logLevel"),
            logPath: self::path($appRoot, self::env('LOG_PATH') ?? 'storage/logs'),
            logRetentionDays: self::positiveInt('LOG_RETENTION_DAYS', 30),
            auditRetentionDays: self::positiveInt('AUDIT_RETENTION_DAYS', 365),
            // /docs et /openapi.json : coupes par defaut, a activer en dev (la spec decrit toute l'API).
            docsEnabled: self::bool('DOCS_ENABLED', false),
        );
    }

    private static function env(string $key): ?string
    {
        $value = getenv($key);
        return $value === false || trim($value) === '' ? null : trim($value);
    }

    private static function positiveInt(string $key, int $default): int
    {
        $raw = self::env($key);
        if ($raw === null) {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RuntimeException("$key must be a positive integer, got '$raw'");
        }
        return $value;
    }

    /** true/false, 1/0, yes/no, on/off ; autre valeur = erreur plutot qu'un false silencieux. */
    private static function bool(string $key, bool $default): bool
    {
        $raw = self::env($key);
        if ($raw === null) {
            return $default;
        }
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw new RuntimeException("$key must be a boolean (true/false), got '$raw'");
    }

    private static function path(string $appRoot, string $path): string
    {
        $path = rtrim($path, '/\\');
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }
        return $appRoot . '/' . $path;
    }
}

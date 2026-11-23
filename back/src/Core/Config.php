<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Http\IpRange;
use App\Core\Log\Level;
use DateTimeZone;
use RuntimeException;

/** Seul endroit du code qui lit getenv() : les autres classes recoivent leurs valeurs. */
final class Config
{
    /**
     * @param array<int, string> $corsOrigins
     * @param list<string> $trustedProxies adresses ou plages CIDR des reverse proxies devant l'API
     */
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
        public readonly string $frontUrl = 'http://localhost:5500',
        public readonly bool $mailDemo = false,
        public readonly string $appEnv = self::ENV_DEV,
        public readonly array $trustedProxies = [],
        public readonly int $rateLimitRegistrations = 5,
        public readonly int $rateLimitRegistrationAttempts = 30,
        public readonly int $rateLimitEmailVerifications = 30,
    ) {
    }

    public const ENV_DEV = 'dev';

    public const ENV_PROD = 'prod';

    /** Production : liens a usage unique jamais renvoyes ni journalises (voir fromEnv et LogMailer). */
    public function isProduction(): bool
    {
        return $this->appEnv === self::ENV_PROD;
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
        $appEnv = self::env('APP_ENV') ?? self::ENV_DEV;
        if (!in_array($appEnv, [self::ENV_DEV, self::ENV_PROD], true)) {
            throw new RuntimeException("APP_ENV must be dev or prod, got '$appEnv'");
        }
        // MAIL_DEMO renvoie le lien de confirmation dans la reponse HTTP : en prod, n'importe qui confirmerait
        // l'adresse d'un autre. Refus au demarrage plutot qu'une faille silencieuse.
        if ($appEnv === self::ENV_PROD && self::bool('MAIL_DEMO', false)) {
            throw new RuntimeException('MAIL_DEMO=true is forbidden when APP_ENV=prod');
        }
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
            // Base des liens envoyes par mail (confirmation d'inscription) : l'adresse du front.
            frontUrl: self::httpUrl('FRONT_URL', 'http://localhost:5500'),
            // Demo sans serveur de mail : l'API renvoie aussi le lien de confirmation. Jamais en prod.
            mailDemo: self::bool('MAIL_DEMO', false),
            appEnv: $appEnv,
            // Vide = pas de proxy : X-Forwarded-For est ignore (voir Http\ClientIp).
            trustedProxies: self::ipRanges('TRUSTED_PROXIES'),
            // Limites par client et par heure. Un campus derriere une seule IP publique (NAT) les partage : a relever
            // si besoin, sans redeployer.
            rateLimitRegistrations: self::positiveInt('RATE_LIMIT_REGISTRATIONS_PER_HOUR', 5),
            rateLimitRegistrationAttempts: self::positiveInt('RATE_LIMIT_REGISTRATION_ATTEMPTS_PER_HOUR', 30),
            rateLimitEmailVerifications: self::positiveInt('RATE_LIMIT_EMAIL_VERIFICATIONS_PER_HOUR', 30),
        );
    }

    /** URL http(s) sans `/` final ; autre valeur = erreur plutot qu'un lien casse dans les mails. */
    private static function httpUrl(string $key, string $default): string
    {
        $raw = self::env($key) ?? $default;
        $scheme = strtolower((string) parse_url($raw, PHP_URL_SCHEME));
        if (filter_var($raw, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException("$key must be an http(s) URL, got '$raw'");
        }
        return rtrim($raw, '/');
    }

    /**
     * Liste separee par des virgules d'adresses IP ou de plages CIDR ; une entree illisible = erreur au demarrage.
     *
     * @return list<string>
     */
    private static function ipRanges(string $key): array
    {
        $ranges = array_values(array_filter(array_map('trim', explode(',', self::env($key) ?? ''))));
        foreach ($ranges as $range) {
            if (!IpRange::isValid($range)) {
                throw new RuntimeException("$key must list IP addresses or CIDR ranges, got '$range'");
            }
        }
        return $ranges;
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

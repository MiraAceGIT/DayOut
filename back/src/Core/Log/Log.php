<?php
declare(strict_types=1);

namespace App\Core\Log;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Utilisable sans configure() (erreur avant le chargement de la config) : niveau info, storage/logs. */
final class Log
{
    private const AUDIT_CHANNEL = 'audit';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private static ?LogWriter $writer = null;
    private static Level $threshold = Level::Info;
    /** @var array<int, string> valeurs masquees dans chaque ligne (ex. DB_PASSWORD) */
    private static array $secrets = [];
    private static ?string $requestId = null;
    private static ?int $userId = null;
    private static bool $writing = false;

    /** @param array<int, string> $secrets */
    public static function configure(
        Level $threshold,
        string $directory,
        int $retentionDays = 30,
        int $auditRetentionDays = 365,
        array $secrets = [],
    ): void {
        self::$threshold = $threshold;
        self::$secrets   = $secrets;
        self::$writer    = new LogWriter($directory, [
            'error'             => $retentionDays,
            'info'              => $retentionDays,
            self::AUDIT_CHANNEL => $auditRetentionDays,
        ]);
    }

    public static function emergency(string $message, array $context = []): void { self::log(Level::Emergency, $message, $context); }
    public static function alert(string $message, array $context = []): void     { self::log(Level::Alert, $message, $context); }
    public static function critical(string $message, array $context = []): void  { self::log(Level::Critical, $message, $context); }
    public static function error(string $message, array $context = []): void     { self::log(Level::Error, $message, $context); }
    public static function warning(string $message, array $context = []): void   { self::log(Level::Warning, $message, $context); }
    public static function notice(string $message, array $context = []): void    { self::log(Level::Notice, $message, $context); }
    public static function info(string $message, array $context = []): void      { self::log(Level::Info, $message, $context); }
    public static function debug(string $message, array $context = []): void     { self::log(Level::Debug, $message, $context); }

    public static function log(Level $level, string $message, array $context = []): void
    {
        if ($level->isAtLeast(self::$threshold)) {
            self::write($level->channel(), $level->value, $message, $context);
        }
    }

    /** Toujours ecrit, quel que soit LOG_LEVEL. $outcome : success | failure | denied. */
    public static function audit(
        string $action,
        string $resourceType,
        int|string|null $resourceId = null,
        string $outcome = 'success',
        array $context = [],
    ): void {
        self::write(self::AUDIT_CHANNEL, Level::Info->value, $action, [
            'resource' => ['type' => $resourceType, 'id' => $resourceId],
            'outcome'  => $outcome,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ] + $context);
    }

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    /** A appeler par le middleware d'auth : rattache les entrees suivantes a l'utilisateur. */
    public static function setUserId(?int $userId): void
    {
        self::$userId = $userId;
    }

    private static function write(string $channel, string $level, string $message, array $context): void
    {
        // Une erreur PHP levee pendant l'ecriture repasserait par ErrorHandler -> Log : on coupe la boucle.
        if (self::$writing) {
            return;
        }
        self::$writing = true;

        try {
            $entry = [
                'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
                'level'     => $level,
                'channel'   => $channel,
                'message'   => $message,
                'requestId' => self::requestId(),
                'userId'    => self::$userId,
                'method'    => $_SERVER['REQUEST_METHOD'] ?? null,
                // Sans query string : elle peut porter des tokens.
                'path'      => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : null,
                'context'   => (object) ContextSanitizer::sanitize($context),
            ];
            $line = ContextSanitizer::scrubSecrets((string) json_encode($entry, self::JSON_FLAGS), self::$secrets);
            self::writer()->append($channel, $line);
        } catch (Throwable $e) {
            // Journaliser ne doit jamais casser la requete : repli sur le log PHP.
            error_log('[Log] ' . $e->getMessage());
        } finally {
            self::$writing = false;
        }
    }

    private static function writer(): LogWriter
    {
        return self::$writer ??= new LogWriter(dirname(__DIR__, 3) . '/storage/logs', [
            'error'             => 30,
            'info'              => 30,
            self::AUDIT_CHANNEL => 365,
        ]);
    }
}

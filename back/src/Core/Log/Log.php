<?php
declare(strict_types=1);

namespace App\Core\Log;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Point d'entree unique de la journalisation :
 *   warning et plus grave -> error.log ; notice, info, debug -> info.log ; audit() -> audit.log.
 * Config .env : LOG_LEVEL, LOG_PATH, LOG_RETENTION_DAYS, AUDIT_RETENTION_DAYS.
 */
final class Log
{
    private const AUDIT_CHANNEL = 'audit';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private static ?LogWriter $writer = null;
    private static ?Level $threshold = null;
    private static ?string $requestId = null;
    private static ?int $userId = null;
    private static bool $writing = false;

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
        if ($level->isAtLeast(self::threshold())) {
            self::write($level->channel(), $level->value, $message, $context);
        }
    }

    /**
     * Action metier sensible, toujours ecrite quel que soit LOG_LEVEL.
     * $outcome : success | failure | denied.
     */
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
            $line = ContextSanitizer::scrubSecrets((string) json_encode($entry, self::JSON_FLAGS));
            self::writer()->append($channel, $line);
        } catch (Throwable $e) {
            // Journaliser ne doit jamais casser la requete : repli sur le log PHP.
            error_log('[Log] ' . $e->getMessage());
        } finally {
            self::$writing = false;
        }
    }

    private static function threshold(): Level
    {
        return self::$threshold ??= Level::tryFrom(strtolower((string) getenv('LOG_LEVEL'))) ?? Level::Info;
    }

    private static function writer(): LogWriter
    {
        $retention = self::days('LOG_RETENTION_DAYS', 30);

        return self::$writer ??= new LogWriter(self::directory(), [
            'error'             => $retention,
            'info'              => $retention,
            self::AUDIT_CHANNEL => self::days('AUDIT_RETENTION_DAYS', 365),
        ]);
    }

    private static function directory(): string
    {
        $path = (string) (getenv('LOG_PATH') ?: 'storage/logs');
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return rtrim($path, '/\\');
        }
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
        return $root . '/' . rtrim($path, '/\\');
    }

    private static function days(string $key, int $default): int
    {
        $value = filter_var(getenv($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? $default : $value;
    }
}

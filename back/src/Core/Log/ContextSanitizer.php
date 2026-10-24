<?php
declare(strict_types=1);

namespace App\Core\Log;

use DateTimeInterface;
use JsonSerializable;
use Throwable;

/** Rend le contexte serialisable en JSON et masque les donnees sensibles. */
final class ContextSanitizer
{
    public const MASK = '[REDACTED]';

    private const SENSITIVE_KEY = '/pass|pwd|secret|token|authorization|cookie|csrf|session|api[-_]?key/i';

    private const MAX_DEPTH = 6;

    public static function sanitize(array $context): array
    {
        return self::sanitizeArray($context, 0);
    }

    /** Filet de securite : un secret connu ne doit jamais sortir, meme cite dans un message libre. */
    public static function scrubSecrets(string $line): string
    {
        $secret = (string) getenv('DB_PASSWORD');
        if (strlen($secret) < 4) {
            return $line;
        }
        $encoded = substr((string) json_encode($secret, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
        return str_replace($encoded, self::MASK, $line);
    }

    private static function sanitizeArray(array $data, int $depth): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1
                ? self::MASK
                : self::value($value, $depth + 1);
        }
        return $clean;
    }

    private static function value(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[MAX_DEPTH]';
        }
        return match (true) {
            $value instanceof Throwable         => self::exception($value, $depth),
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof JsonSerializable  => self::value($value->jsonSerialize(), $depth),
            is_array($value)                    => self::sanitizeArray($value, $depth),
            is_object($value)                   => $value::class,
            is_resource($value)                 => 'resource',
            default                             => $value,
        };
    }

    private static function exception(Throwable $e, int $depth): array
    {
        $data = [
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile() . ':' . $e->getLine(),
            'trace'   => self::trace($e),
        ];
        if ($e->getPrevious() !== null) {
            $data['previous'] = self::value($e->getPrevious(), $depth + 1);
        }
        return $data;
    }

    /** Trace reconstruite sans les arguments : getTraceAsString() peut exposer le mot de passe passe a new PDO(). */
    private static function trace(Throwable $e): array
    {
        return array_map(
            static fn (array $frame): string => sprintf(
                '%s:%s %s%s%s()',
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function']
            ),
            $e->getTrace()
        );
    }
}

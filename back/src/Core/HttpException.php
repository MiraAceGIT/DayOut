<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/** Seule exception dont le message est renvoye au client : jamais de donnee interne dedans. */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        ?Throwable $previous = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function badRequest(string $message, array $details = [], ?Throwable $previous = null): self
    {
        return new self(400, 'BAD_REQUEST', $message, $details, $previous);
    }

    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self(401, 'UNAUTHORIZED', $message, [], null, ['WWW-Authenticate' => 'Bearer']);
    }

    public static function notFound(string $message, array $details = []): self
    {
        return new self(404, 'NOT_FOUND', $message, $details);
    }

    /** @param array<int, string> $allowed */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'METHOD_NOT_ALLOWED', 'Method not allowed on this route', [], null,
            ['Allow' => implode(', ', $allowed)]);
    }
}

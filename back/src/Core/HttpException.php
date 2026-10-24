<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Erreur destinee au client : ErrorHandler la renvoie telle quelle
 * ({ "error": { code, message, details? } }) avec le statut HTTP fourni.
 * Toute autre exception devient un 500 generique.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function badRequest(string $message, array $details = [], ?Throwable $previous = null): self
    {
        return new self(400, 'BAD_REQUEST', $message, $details, $previous);
    }

    public static function notFound(string $message, array $details = []): self
    {
        return new self(404, 'NOT_FOUND', $message, $details);
    }
}

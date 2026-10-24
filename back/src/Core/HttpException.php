<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Erreur destinee au client : ExceptionRenderer la renvoie telle quelle
 * ({ "error": { code, message, details? } }) avec le statut HTTP et les en-tetes fournis.
 * Toute autre exception devient un 500 generique.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers ex. ['Allow' => 'GET'] pour un 405 */
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

    public static function notFound(string $message, array $details = []): self
    {
        return new self(404, 'NOT_FOUND', $message, $details);
    }

    /** @param array<int, string> $allowed methodes acceptees par la route */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'METHOD_NOT_ALLOWED', 'Method not allowed on this route', [], null,
            ['Allow' => implode(', ', $allowed)]);
    }

    /** @param array<string, string> $errors champ => message */
    public static function validation(array $errors): self
    {
        return new self(422, 'VALIDATION_ERROR', 'Invalid input', $errors);
    }
}

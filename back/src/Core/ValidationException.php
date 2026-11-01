<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Donnees recues invalides, sans dependance a HTTP : levee par le Validator, les mappers et les DAO,
 * traduite en 422 VALIDATION_ERROR par ExceptionRenderer. Les messages partent au client : jamais de donnee interne.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors champ => message */
    public function __construct(public readonly array $errors, string $message = 'Invalid input')
    {
        parent::__construct($message);
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}

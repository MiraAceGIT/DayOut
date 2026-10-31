<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

use RuntimeException;
use Throwable;

abstract class BusinessException extends RuntimeException
{
    protected function __construct(
        public readonly ErrorKind $kind,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

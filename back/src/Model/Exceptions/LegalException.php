<?php declare(strict_types=1);

namespace App\Model\Exceptions;

final class LegalException extends BusinessException
{
    public static function notFound(string $type): self
    {
        return new self(ErrorKind::NotFound, 'LEGAL_PAGE_NOT_FOUND', 'Legal page not found', ['type' => $type]);
    }
}
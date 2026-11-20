<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

use App\Core\BusinessException;
use App\Core\ErrorKind;

final class FaqException extends BusinessException
{
    public static function notFound(int $id): self
    {
        return new self(ErrorKind::NotFound, 'FAQ_NOT_FOUND', 'FAQ entry not found', ['id' => $id]);
    }

    public static function adminRequired(): self
    {
        return new self(ErrorKind::Forbidden, 'FAQ_ADMIN_REQUIRED', 'Only administrators can manage FAQ entries');
    }
}

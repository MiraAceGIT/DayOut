<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

final class EventException extends BusinessException
{
    public static function notFound(int $id): self
    {
        return new self(ErrorKind::NotFound, 'EVENT_NOT_FOUND', 'Event not found', ['id' => $id]);
    }
}

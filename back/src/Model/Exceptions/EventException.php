<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

final class EventException extends BusinessException
{
    public static function notFound(int $id): self
    {
        return new self(ErrorKind::NotFound, 'EVENT_NOT_FOUND', 'Event not found', ['id' => $id]);
    }

    public static function notOwner(int $id): self
    {
        return new self(ErrorKind::Forbidden, 'EVENT_FORBIDDEN', 'Only the creator of this event can change it', ['id' => $id]);
    }

    public static function creatorRequired(): self
    {
        return new self(ErrorKind::Forbidden, 'CREATOR_REQUIRED', 'Only approved creators can create events');
    }

    public static function hasRegistrations(int $id): self
    {
        return new self(ErrorKind::Conflict, 'EVENT_HAS_REGISTRATIONS', 'Event has registrations and cannot be deleted', ['id' => $id]);
    }
}

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
        return new self(ErrorKind::Forbidden, 'CREATOR_REQUIRED', 'Only approved creators can manage events');
    }

    public static function invalidStatus(int $id, string $status, string $action): self
    {
        return new self(ErrorKind::Conflict, 'EVENT_INVALID_STATUS', "Cannot $action an event that is $status",
            ['id' => $id, 'status' => $status, 'action' => $action]);
    }

    /** @param list<string> $fields */
    public static function fieldsLocked(int $id, array $fields, string $reason): self
    {
        return new self(ErrorKind::Conflict, 'EVENT_FIELDS_LOCKED', "These fields can no longer be changed: $reason",
            ['id' => $id, 'fields' => $fields]);
    }
}

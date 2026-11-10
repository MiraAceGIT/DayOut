<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

final class TransportException extends BusinessException
{
    public static function eventHasNoCoordinates(int $eventId): self
    {
        return new self(
            ErrorKind::Invalid,
            'EVENT_NO_COORDINATES',
            'This event has no coordinates, transport info is unavailable',
            ['id' => $eventId],
        );
    }
}

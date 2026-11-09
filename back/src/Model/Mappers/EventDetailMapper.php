<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\EventDetailDto;

final class EventDetailMapper
{
    public static function toDto(array $row): EventDetailDto
    {
        return new EventDetailDto(EventMapper::toDto($row), CreatorMapper::toDto($row));
    }

    /** @return list<EventDetailDto> */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }
}

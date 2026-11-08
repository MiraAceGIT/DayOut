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
}

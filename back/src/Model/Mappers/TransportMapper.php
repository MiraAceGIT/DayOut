<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\Transport\TransportOriginDto;
use App\Model\Dto\Transport\TransportStopDto;
use App\Model\Transport\TransportStop;

final class TransportMapper
{
    /** @param array<string, mixed> $schoolRow ligne hydratee de `schools` */
    public static function originFromSchool(array $schoolRow): TransportOriginDto
    {
        return new TransportOriginDto(
            type: 'school',
            name: $schoolRow['short_name'] ?? $schoolRow['name'],
            latitude: $schoolRow['latitude'],
            longitude: $schoolRow['longitude'],
        );
    }

    public static function stopToDto(TransportStop $stop): TransportStopDto
    {
        return new TransportStopDto(
            name: $stop->name,
            type: $stop->type,
            latitude: $stop->latitude,
            longitude: $stop->longitude,
            distanceM: $stop->distanceM,
            lines: $stop->lines,
        );
    }

    /**
     * @param list<TransportStop> $stops
     * @return list<TransportStopDto>
     */
    public static function stopsToDtos(array $stops): array
    {
        return array_map(self::stopToDto(...), $stops);
    }
}

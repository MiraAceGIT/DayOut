<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\School\SchoolDto;

final class SchoolMapper
{
    public static function toDto(array $row): SchoolDto
    {
        return new SchoolDto($row['id'], $row['name'], $row['short_name'], $row['email_domain'], $row['city']);
    }

    /** @return list<SchoolDto> */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }
}

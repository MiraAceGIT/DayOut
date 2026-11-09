<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\Creator\CreatorDto;

final class CreatorMapper
{
    public static function toDto(array $row): CreatorDto
    {
        return new CreatorDto(
            id: (int) $row['detail_creator_id'],
            displayName: $row['detail_display_name'] ?? trim($row['detail_first_name'] . ' ' . $row['detail_last_name']),
            description: $row['detail_creator_description'] ?? $row['detail_bio'],
            avatarUrl: $row['detail_avatar_url'],
            schoolName: $row['detail_school_short_name'] ?? $row['detail_school_name'],
        );
    }

    /** @return list<CreatorDto> */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }
}

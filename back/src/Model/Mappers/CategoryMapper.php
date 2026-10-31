<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\CategoryDto;

final class CategoryMapper
{
    /** @param array{slug: string, label: string} $row */
    public static function toDto(array $row): CategoryDto
    {
        return new CategoryDto(slug: $row['slug'], label: $row['label']);
    }

    /**
     * @param array<int, array{slug: string, label: string}> $rows
     * @return list<CategoryDto>
     */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }

    /** @return array{slug: string, label: string} colonnes de `categories` */
    public static function toRow(CategoryDto $dto): array
    {
        return ['slug' => $dto->slug, 'label' => $dto->label];
    }
}

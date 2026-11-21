<?php declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\Legal\LegalPageDto;

final class LegalPageMapper
{
    /**
     * @param array<string, mixed> $row
     */

    public static function toDo(array $row): LegalPageDto
    {
        return new LegalPageDto(
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            bodyMd: (string) $row['body_md'],
            updatedAt: $row ['updated_at']
        );
    }
}

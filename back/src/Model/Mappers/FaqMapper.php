<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqDto;
use App\Model\Dto\Faq\FaqUpdateDto;

final class FaqMapper
{
    public static function toDto(array $row): FaqDto
    {
        return new FaqDto($row['id'], $row['question'], $row['answer'], $row['sort_order'], $row['active']);
    }

    /** @return list<FaqDto> */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }

    /** @return array<string, mixed> */
    public static function toRow(FaqCreateDto $dto): array
    {
        return self::columns($dto);
    }

    /** @return array<string, mixed> seulement les champs envoyes (`provided`) */
    public static function toUpdateRow(FaqUpdateDto $dto): array
    {
        return array_intersect_key(self::columns($dto), array_flip($dto->provided));
    }

    /** @return array<string, mixed> seule liste des colonnes ecrites */
    private static function columns(FaqCreateDto|FaqUpdateDto $dto): array
    {
        return [
            'question'   => $dto->question,
            'answer'     => $dto->answer,
            'sort_order' => $dto->sortOrder,
            'active'     => $dto->active,
        ];
    }
}

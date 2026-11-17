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

    public static function toRow(FaqCreateDto $dto): array
    {
        return [
            'question' => $dto->question,
            'answer' => $dto->answer,
            'sort_order' => $dto->sortOrder,
            'active' => $dto->active,
        ];
    }

    public static function toUpdateRow(FaqUpdateDto $dto): array
    {
        return array_intersect_key([
            'question' => $dto->question,
            'answer' => $dto->answer,
            'sort_order' => $dto->sortOrder,
            'active' => $dto->active,
        ], array_flip($dto->provided));
    }
}

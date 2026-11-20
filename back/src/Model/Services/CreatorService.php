<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Creator\CreatorSearchParams;
use App\Model\Dto\Creator\CreatorDto;
use App\Model\Dto\PageDto;
use App\Model\Mappers\CreatorMapper;

final class CreatorService
{
    public function __construct(private readonly Creator $creators)
    {
    }

    /** @return PageDto<CreatorDto> */
    public function findMany(CreatorSearchParams $params): PageDto
    {
        [$rows, $total] = $this->creators->findManyPublic($params);

        return PageDto::of(CreatorMapper::toDtos($rows), $params->page, $total);
    }
}

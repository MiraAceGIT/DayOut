<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\School\School;
use App\Model\Dto\School\SchoolDto;
use App\Model\Mappers\SchoolMapper;

final class SchoolService
{
    public function __construct(private readonly School $schools)
    {
    }

    /** @return list<SchoolDto> */
    public function findActive(): array
    {
        return SchoolMapper::toDtos($this->schools->listActive());
    }
}

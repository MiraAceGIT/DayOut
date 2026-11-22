<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category\Category;
use App\Model\Dto\Category\CategoryDto;
use App\Model\Mappers\CategoryMapper;

final class CategoryService
{
    public function __construct(private readonly Category $categories)
    {
    }

    /** @return list<CategoryDto> */
    public function findActive(): array
    {
        return CategoryMapper::toDtos($this->categories->listActive());
    }
}

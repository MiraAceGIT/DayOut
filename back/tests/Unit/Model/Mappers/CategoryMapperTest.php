<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Mappers;

use App\Model\Mappers\CategoryMapper;
use Tests\TestCase;

final class CategoryMapperTest extends TestCase
{
    public function testCategoryRoundTrip(): void
    {
        $dto = CategoryMapper::toDto(['slug' => 'sport', 'label' => 'Sport', 'event_id' => 7]);

        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], $dto->jsonSerialize());
        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], CategoryMapper::toRow($dto));
        $this->assertSame([0], array_keys(CategoryMapper::toDtos([3 => ['slug' => 'a', 'label' => 'A']])), 're-indexed as a list');
    }
}

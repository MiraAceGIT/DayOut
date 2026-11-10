<?php
declare(strict_types=1);

namespace App\Model\Dto\Category;

use JsonSerializable;

final readonly class CategoryDto implements JsonSerializable
{
    public function __construct(
        public string $slug,
        public string $label,
    ) {
    }

    /** @return array{slug: string, label: string} */
    public function jsonSerialize(): array
    {
        return ['slug' => $this->slug, 'label' => $this->label];
    }
}

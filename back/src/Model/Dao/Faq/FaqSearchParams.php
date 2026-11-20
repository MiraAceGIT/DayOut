<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

final readonly class FaqSearchParams
{
    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $text = null,
        public ?array $ids = null,
        public string $sortBy = 'sort_order',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}

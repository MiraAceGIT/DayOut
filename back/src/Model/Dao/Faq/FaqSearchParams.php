<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

use App\Model\Dao\PageRequest;

final readonly class FaqSearchParams
{
    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $text = null,
        public ?array $ids = null,
        public PageRequest $page = new PageRequest(),
    ) {
    }
}

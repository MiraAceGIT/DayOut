<?php
declare(strict_types=1);

namespace App\Model\Dao\Creator;

use App\Model\Dao\PageRequest;
use DateTimeImmutable;

final readonly class CreatorSearchParams
{
    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $text = null,
        public ?int $schoolId = null,
        public ?array $ids = null,
        public ?DateTimeImmutable $withEventsEndingAfter = null,
        public PageRequest $page = new PageRequest(),
    ) {
    }
}

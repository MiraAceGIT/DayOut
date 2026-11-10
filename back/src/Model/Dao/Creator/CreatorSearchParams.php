<?php
declare(strict_types=1);

namespace App\Model\Dao\Creator;

use App\Model\Dao\Model;
use DateTimeImmutable;

final readonly class CreatorSearchParams
{
    /**
     * @param list<int>|null     $ids
     * @param ?DateTimeImmutable $withEventsEndingAfter
     * @param string             $sortBy
     */
    public function __construct(
        public ?string $text = null,
        public ?int $schoolId = null,
        public ?array $ids = null,
        public ?DateTimeImmutable $withEventsEndingAfter = null,
        public string $sortBy = 'display_name',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}

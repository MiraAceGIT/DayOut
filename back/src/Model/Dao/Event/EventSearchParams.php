<?php
declare(strict_types=1);

namespace App\Model\Dao\Event;

use App\Model\Dao\Model;
use DateTimeImmutable;

final readonly class EventSearchParams
{
    /**
     * @param list<int>|null    $ids
     * @param list<string>|null $categories
     */
    public function __construct(
        public ?DateTimeImmutable $endingAfter = null,
        public ?DateTimeImmutable $startFrom = null,
        public ?DateTimeImmutable $startTo = null,
        public ?string $text = null,
        public ?string $city = null,
        public ?array $ids = null,
        public ?array $categories = null,
        public ?int $creatorId = null,
        public ?bool $isFree = null,
        public bool $onlyAvailable = false,
        public string $sortBy = 'start_at',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}

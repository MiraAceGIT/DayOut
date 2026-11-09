<?php
declare(strict_types=1);

namespace App\Model\Dao;

use DateTimeImmutable;

/** Criteres deja valides consommes par Event::findManyPublished ; null = critere inactif. */
final readonly class EventSearchParams
{
    /**
     * @param list<int>|null    $ids
     * @param list<string>|null $categories slugs (OU entre eux)
     * @param string            $sortBy     cle de Event::SORT_MAP (inconnue = start_at)
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

<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;
use App\Model\Dao\Event\EventSearchParams;
use DateTimeImmutable;

final readonly class EventFindQuery
{
    use ParsesQueryString;

    public const RULES = [
        'from'       => 'datetime',
        'start_from' => 'datetime',
        'start_to'   => 'datetime',
        'q'          => 'string|max:100',
        'city'       => 'string|max:80',
        'creator_id' => 'int|min:1',
        'is_free'    => 'bool',
        'available'  => 'bool',
        'sort_by'    => 'in:start_at,created_at,title,price_cents',
        'sort_dir'   => 'in:asc,desc',
        'limit'      => 'int|min:1|max:100',
        'offset'     => 'int|min:0|max:10000',
    ];

    /**
     * @param list<int>|null    $ids
     * @param list<string>|null $categories
     */
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $startFrom = null,
        public ?DateTimeImmutable $startTo = null,
        public ?string $q = null,
        public ?string $city = null,
        public ?array $ids = null,
        public ?array $categories = null,
        public ?int $creatorId = null,
        public ?bool $isFree = null,
        public bool $available = false,
        public string $sortBy = 'start_at',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1, 'categories' => 1]), self::RULES);

        return new self(
            from:       self::date($clean['from'] ?? null),
            startFrom:  self::date($clean['start_from'] ?? null),
            startTo:    self::date($clean['start_to'] ?? null),
            q:          self::text($clean['q'] ?? null),
            city:       self::text($clean['city'] ?? null),
            ids:        self::parseIds('ids', $query['ids'] ?? null),
            categories: self::parseSlugs('categories', $query['categories'] ?? null),
            creatorId:  $clean['creator_id'] ?? null,
            isFree:     $clean['is_free'] ?? null,
            available:  $clean['available'] ?? false,
            sortBy:     $clean['sort_by'] ?? 'start_at',
            sortDir:    $clean['sort_dir'] ?? 'asc',
            limit:      $clean['limit'] ?? 20,
            offset:     $clean['offset'] ?? 0,
        );
    }

    public function toParams(?DateTimeImmutable $now = null): EventSearchParams
    {
        return new EventSearchParams(
            endingAfter:   $this->from ?? $now ?? new DateTimeImmutable(),
            startFrom:     $this->startFrom,
            startTo:       $this->startTo,
            text:          $this->q,
            city:          $this->city,
            ids:           $this->ids,
            categories:    $this->categories,
            creatorId:     $this->creatorId,
            isFree:        $this->isFree,
            onlyAvailable: $this->available,
            sortBy:        $this->sortBy,
            sortDir:       $this->sortDir,
            limit:         $this->limit,
            offset:        $this->offset,
        );
    }
}

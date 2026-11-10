<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;
use App\Model\Dao\Creator\CreatorSearchParams;
use DateTimeImmutable;

final readonly class CreatorFindQuery
{
    use ParsesQueryString;

    public const RULES = [
        'q'                   => 'string|max:100',
        'school_id'           => 'int|min:1',
        'has_upcoming_events' => 'bool',
        'from'                => 'datetime',
        'sort_by'             => 'in:display_name,created_at',
        'sort_dir'            => 'in:asc,desc',
        'limit'               => 'int|min:1|max:100',
        'offset'              => 'int|min:0|max:10000',
    ];

    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $q = null,
        public ?int $schoolId = null,
        public ?array $ids = null,
        public bool $hasUpcomingEvents = false,
        public ?DateTimeImmutable $from = null,
        public string $sortBy = 'display_name',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1]), self::RULES);

        return new self(
            q:                 self::text($clean['q'] ?? null),
            schoolId:          $clean['school_id'] ?? null,
            ids:               self::parseIds('ids', $query['ids'] ?? null),
            hasUpcomingEvents: $clean['has_upcoming_events'] ?? false,
            from:              self::date($clean['from'] ?? null),
            sortBy:            $clean['sort_by'] ?? 'display_name',
            sortDir:           $clean['sort_dir'] ?? 'asc',
            limit:             $clean['limit'] ?? 20,
            offset:            $clean['offset'] ?? 0,
        );
    }

    public function toParams(?DateTimeImmutable $now = null): CreatorSearchParams
    {
        return new CreatorSearchParams(
            text:                  $this->q,
            schoolId:              $this->schoolId,
            ids:                   $this->ids,
            withEventsEndingAfter: $this->hasUpcomingEvents ? ($this->from ?? $now ?? new DateTimeImmutable()) : null,
            sortBy:                $this->sortBy,
            sortDir:               $this->sortDir,
            limit:                 $this->limit,
            offset:                $this->offset,
        );
    }
}

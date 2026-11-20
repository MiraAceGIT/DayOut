<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;
use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Creator\CreatorSearchParams;
use App\Model\Dao\PageRequest;
use DateTimeImmutable;

final readonly class CreatorFindQuery
{
    use ParsesQueryString;

    /** Filtres propres aux createurs ; tri et pagination viennent de pageRules(). */
    private const FILTERS = [
        'q'                   => 'string|max:100',
        'school_id'           => 'int|min:1',
        'has_upcoming_events' => 'bool',
        'from'                => 'datetime',
    ];

    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $q = null,
        public ?int $schoolId = null,
        public ?array $ids = null,
        public bool $hasUpcomingEvents = false,
        public ?DateTimeImmutable $from = null,
        public PageRequest $page = new PageRequest(),
    ) {
    }

    /** @return array<string, string> */
    public static function rules(): array
    {
        return self::FILTERS + self::pageRules(Creator::SORT_MAP);
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1]), self::rules());

        return new self(
            q:                 self::text($clean['q'] ?? null),
            schoolId:          $clean['school_id'] ?? null,
            ids:               self::parseIds('ids', $query['ids'] ?? null),
            hasUpcomingEvents: $clean['has_upcoming_events'] ?? false,
            from:              self::date($clean['from'] ?? null),
            page:              self::page($clean, Creator::SORT_MAP),
        );
    }

    public function toParams(?DateTimeImmutable $now = null): CreatorSearchParams
    {
        return new CreatorSearchParams(
            text:                  $this->q,
            schoolId:              $this->schoolId,
            ids:                   $this->ids,
            withEventsEndingAfter: $this->hasUpcomingEvents ? ($this->from ?? $now ?? new DateTimeImmutable()) : null,
            page:                  $this->page,
        );
    }
}

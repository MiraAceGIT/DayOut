<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;
use App\Model\Dao\Faq\FaqSearchParams;

final readonly class FaqFindQuery
{
    use ParsesQueryString;

    public const RULES = [
        'q'        => 'string|max:100',
        'sort_by'  => 'in:sort_order,question,created_at',
        'sort_dir' => 'in:asc,desc',
        'limit'    => 'int|min:1|max:100',
        'offset'   => 'int|min:0|max:10000',
    ];

    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $q = null,
        public ?array $ids = null,
        public string $sortBy = 'sort_order',
        public string $sortDir = 'asc',
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }

    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1]), self::RULES);

        return new self(
            q: self::text($clean['q'] ?? null),
            ids: self::parseIds('ids', $query['ids'] ?? null),
            sortBy: $clean['sort_by'] ?? 'sort_order',
            sortDir: $clean['sort_dir'] ?? 'asc',
            limit: $clean['limit'] ?? 20,
            offset: $clean['offset'] ?? 0,
        );
    }

    public function toParams(): FaqSearchParams
    {
        return new FaqSearchParams(
            text: $this->q,
            ids: $this->ids,
            sortBy: $this->sortBy,
            sortDir: $this->sortDir,
            limit: $this->limit,
            offset: $this->offset,
        );
    }
}

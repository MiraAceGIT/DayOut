<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;
use App\Model\Dao\Faq\Faq;
use App\Model\Dao\Faq\FaqSearchParams;
use App\Model\Dao\PageRequest;

final readonly class FaqFindQuery
{
    use ParsesQueryString;

    /** Filtres propres a la FAQ ; tri et pagination viennent de pageRules(). */
    private const FILTERS = [
        'q' => 'string|max:100',
    ];

    /** @param list<int>|null $ids */
    public function __construct(
        public ?string $q = null,
        public ?array $ids = null,
        public PageRequest $page = new PageRequest(),
    ) {
    }

    /** @return array<string, string> */
    public static function rules(): array
    {
        return self::FILTERS + self::pageRules(Faq::SORT_MAP);
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1]), self::rules());

        return new self(
            q:    self::text($clean['q'] ?? null),
            ids:  self::parseIds('ids', $query['ids'] ?? null),
            page: self::page($clean, Faq::SORT_MAP),
        );
    }

    public function toParams(): FaqSearchParams
    {
        return new FaqSearchParams(
            text: $this->q,
            ids:  $this->ids,
            page: $this->page,
        );
    }
}

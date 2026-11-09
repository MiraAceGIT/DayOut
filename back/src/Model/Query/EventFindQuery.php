<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\ValidationException;
use App\Core\Validator;
use App\Model\Dao\EventSearchParams;
use DateTimeImmutable;

/**
 * Filtres de GET /events lus dans la query string. Listes en CSV (`categories=soiree,sport`)
 * ou repetees (`categories[]=soiree&categories[]=sport`). Toute valeur invalide donne 422.
 */
final readonly class EventFindQuery
{
    private const MAX_LIST_ITEMS = 50;

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

    /** @param array<string, mixed> $query typiquement $request->query() */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1, 'categories' => 1]), self::RULES);

        return new self(
            from:       self::date($clean['from'] ?? null),
            startFrom:  self::date($clean['start_from'] ?? null),
            startTo:    self::date($clean['start_to'] ?? null),
            q:          self::text($clean['q'] ?? null),
            city:       self::text($clean['city'] ?? null),
            ids:        self::parseIds($query['ids'] ?? null),
            categories: self::parseSlugs($query['categories'] ?? null),
            creatorId:  $clean['creator_id'] ?? null,
            isFree:     $clean['is_free'] ?? null,
            available:  $clean['available'] ?? false,
            sortBy:     $clean['sort_by'] ?? 'start_at',
            sortDir:    $clean['sort_dir'] ?? 'asc',
            limit:      $clean['limit'] ?? 20,
            offset:     $clean['offset'] ?? 0,
        );
    }

    /** `$now` injectable pour les tests ; `from` absent = maintenant (masque les events termines). */
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

    /** @return list<int>|null */
    private static function parseIds(mixed $value): ?array
    {
        $parts = self::csv('ids', $value);
        if ($parts === null) {
            return null;
        }
        $ids = [];
        foreach ($parts as $part) {
            $id = preg_match('/^[1-9][0-9]*$/D', $part) === 1 ? filter_var($part, FILTER_VALIDATE_INT) : false;
            if ($id === false) {
                throw ValidationException::field('ids', 'Must be a comma-separated list of positive integers');
            }
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    /** @return list<string>|null */
    private static function parseSlugs(mixed $value): ?array
    {
        $parts = self::csv('categories', $value);
        if ($parts === null) {
            return null;
        }
        foreach ($parts as $part) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $part) !== 1 || strlen($part) > 60) {
                throw ValidationException::field('categories', 'Must be a comma-separated list of category slugs');
            }
        }
        return array_values(array_unique($parts));
    }

    /**
     * "a, b,,c" ou ['a', 'b'] -> ['a', 'b', 'c'] ; vide -> null (critere inactif).
     *
     * @return list<string>|null
     */
    private static function csv(string $field, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value) || array_filter($value, static fn (mixed $v): bool => !is_string($v)) !== []) {
            throw ValidationException::field($field, 'Must be a comma-separated list');
        }
        $parts = array_values(array_filter(array_map('trim', $value), static fn (string $v): bool => $v !== ''));
        if (count($parts) > self::MAX_LIST_ITEMS) {
            throw ValidationException::field($field, 'Must contain at most ' . self::MAX_LIST_ITEMS . ' items');
        }
        return $parts === [] ? null : $parts;
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);
        return $value === '' ? null : $value;
    }
}

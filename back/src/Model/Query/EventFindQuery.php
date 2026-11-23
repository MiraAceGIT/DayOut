<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\ValidationException;
use App\Core\Validator;
use App\Model\Dao\Event\Event;
use App\Model\Dao\Event\EventSearchParams;
use App\Model\Dao\PageRequest;
use DateTimeImmutable;

final readonly class EventFindQuery
{
    use ParsesQueryString;

    public const MIN_RADIUS_KM = 1;
    public const MAX_RADIUS_KM = 20;

    /** Filtres propres aux events ; tri et pagination viennent de pageRules(). */
    private const FILTERS = [
        'from'       => 'datetime',
        'start_from' => 'datetime',
        'start_to'   => 'datetime',
        'q'          => 'string|max:100',
        'city'       => 'string|max:80',
        'creator_id' => 'int|min:1',
        'is_free'    => 'bool',
        'available'  => 'bool',
        'lat'        => 'number|min:-90|max:90',
        'lng'        => 'number|min:-180|max:180',
        'radius'     => 'number|min:' . self::MIN_RADIUS_KM . '|max:' . self::MAX_RADIUS_KM,
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
        public ?float $lat = null,
        public ?float $lng = null,
        public ?float $radius = null,
        public PageRequest $page = new PageRequest(),
    ) {
    }

    /** @return array<string, string> */
    public static function rules(): array
    {
        return self::FILTERS + self::pageRules(Event::SORT_MAP);
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $clean = Validator::check(array_diff_key($query, ['ids' => 1, 'categories' => 1]), self::rules());

        if (isset($clean['start_from'], $clean['start_to'])
            && new DateTimeImmutable($clean['start_to']) <= new DateTimeImmutable($clean['start_from'])) {
            throw ValidationException::field('start_to', 'Must be after start_from');
        }

        // Recherche geographique : les trois parametres vont ensemble, sinon la requete est ambigue.
        $geoKeys  = ['lat', 'lng', 'radius'];
        $geoGiven = array_values(array_filter($geoKeys, static fn (string $k): bool => array_key_exists($k, $clean)));
        if ($geoGiven !== [] && count($geoGiven) !== count($geoKeys)) {
            $missing = array_values(array_diff($geoKeys, $geoGiven));
            throw ValidationException::field($missing[0], 'Requires lat, lng and radius together');
        }

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
            lat:        $clean['lat'] ?? null,
            lng:        $clean['lng'] ?? null,
            radius:     $clean['radius'] ?? null,
            page:       self::page($clean, Event::SORT_MAP),
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
            latitude:      $this->lat,
            longitude:     $this->lng,
            radiusKm:      $this->radius,
            page:          $this->page,
        );
    }
}

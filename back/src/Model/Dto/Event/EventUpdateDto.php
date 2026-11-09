<?php
declare(strict_types=1);

namespace App\Model\Dto\Event;

use App\Core\ValidationException;
use App\Core\Validator;

final readonly class EventUpdateDto
{
    public const RULES = [
        'title'                => 'string|max:180',
        'slug'                 => 'string|slug|max:200',
        'description'          => 'string|max:16000',
        'cover_image_url'      => 'nullable|url|max:255',
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'venue_name'           => 'nullable|string|max:160',
        'address_line'         => 'nullable|string|max:200',
        'city'                 => 'nullable|string|max:80',
        'postal_code'          => 'nullable|string|max:20',
        'latitude'             => 'nullable|number|min:-90|max:90',
        'longitude'            => 'nullable|number|min:-180|max:180',
        'capacity'             => 'nullable|int|min:1|max:' . EventCreateDto::INT_UNSIGNED_MAX,
        'price_cents'          => 'int|min:0|max:' . EventCreateDto::INT_UNSIGNED_MAX,
        'currency'             => 'in:EUR',
        'is_paid'              => 'bool',
        'external_ticket_url'  => 'nullable|url|max:255',
        'restricted_school_id' => 'nullable|int|min:1|max:' . EventCreateDto::INT_UNSIGNED_MAX,
    ];

    /** @param list<string> $provided */
    public function __construct(
        public array $provided = [],
        public ?string $title = null,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $startAt = null,
        public ?string $endAt = null,
        public ?string $coverImageUrl = null,
        public ?string $venueName = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $capacity = null,
        public ?int $priceCents = null,
        public ?string $currency = null,
        public ?bool $isPaid = null,
        public ?string $externalTicketUrl = null,
        public ?int $restrictedSchoolId = null,
    ) {
    }

    public static function fromArray(array $json): self
    {
        $v = Validator::check($json, self::RULES);
        if ($v === []) {
            throw ValidationException::field('body', 'No writable field (allowed: ' . implode(', ', array_keys(self::RULES)) . ')');
        }
        if (isset($v['start_at'], $v['end_at'])) {
            EventCreateDto::checkDates($v['start_at'], $v['end_at']);
        }
        if (isset($v['is_paid'], $v['price_cents'])) {
            EventCreateDto::checkPrice($v['is_paid'], $v['price_cents']);
        }

        return new self(
            provided: array_keys($v),
            title: $v['title'] ?? null,
            slug: $v['slug'] ?? null,
            description: $v['description'] ?? null,
            startAt: $v['start_at'] ?? null,
            endAt: $v['end_at'] ?? null,
            coverImageUrl: $v['cover_image_url'] ?? null,
            venueName: $v['venue_name'] ?? null,
            addressLine: $v['address_line'] ?? null,
            city: $v['city'] ?? null,
            postalCode: $v['postal_code'] ?? null,
            latitude: $v['latitude'] ?? null,
            longitude: $v['longitude'] ?? null,
            capacity: $v['capacity'] ?? null,
            priceCents: $v['price_cents'] ?? null,
            currency: $v['currency'] ?? null,
            isPaid: $v['is_paid'] ?? null,
            externalTicketUrl: $v['external_ticket_url'] ?? null,
            restrictedSchoolId: $v['restricted_school_id'] ?? null,
        );
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }
}

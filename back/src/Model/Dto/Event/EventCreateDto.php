<?php
declare(strict_types=1);

namespace App\Model\Dto\Event;

use App\Core\ValidationException;
use App\Core\Validator;
use DateTimeImmutable;

final readonly class EventCreateDto
{

    public const RULES = [
        'title'                => 'required|string|max:180',
        'slug'                 => 'required|string|slug|max:200',
        'description'          => 'required|string|max:16000',
        'cover_image_url'      => 'nullable|url|max:255',
        'start_at'             => 'required|datetime',
        'end_at'               => 'required|datetime',
        'venue_name'           => 'nullable|string|max:160',
        'address_line'         => 'nullable|string|max:200',
        'city'                 => 'nullable|string|max:80',
        'postal_code'          => 'nullable|string|max:20',
        'latitude'             => 'nullable|number|min:-90|max:90',
        'longitude'            => 'nullable|number|min:-180|max:180',
        'capacity'             => 'nullable|int|min:1',
        'price_cents'          => 'int|min:0',
        'currency'             => 'in:EUR',
        'is_paid'              => 'bool',
        'external_ticket_url'  => 'nullable|url|max:255',
        'restricted_school_id' => 'nullable|int|min:1',
    ];

    public function __construct(
        public string $title,
        public string $slug,
        public string $description,
        public string $startAt,
        public string $endAt,
        public ?string $coverImageUrl = null,
        public ?string $venueName = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $capacity = null,
        public int $priceCents = 0,
        public string $currency = 'EUR',
        public bool $isPaid = false,
        public ?string $externalTicketUrl = null,
        public ?int $restrictedSchoolId = null,
    ) {
    }

    public static function fromArray(array $json): self
    {
        $v = Validator::check($json, self::RULES);
        self::checkDates($v['start_at'], $v['end_at']);
        self::checkPrice($v['is_paid'] ?? false, $v['price_cents'] ?? 0);

        return new self(
            title: $v['title'],
            slug: $v['slug'],
            description: $v['description'],
            startAt: $v['start_at'],
            endAt: $v['end_at'],
            coverImageUrl: $v['cover_image_url'] ?? null,
            venueName: $v['venue_name'] ?? null,
            addressLine: $v['address_line'] ?? null,
            city: $v['city'] ?? null,
            postalCode: $v['postal_code'] ?? null,
            latitude: $v['latitude'] ?? null,
            longitude: $v['longitude'] ?? null,
            capacity: $v['capacity'] ?? null,
            priceCents: $v['price_cents'] ?? 0,
            currency: $v['currency'] ?? 'EUR',
            isPaid: $v['is_paid'] ?? false,
            externalTicketUrl: $v['external_ticket_url'] ?? null,
            restrictedSchoolId: $v['restricted_school_id'] ?? null,
        );
    }

    public static function checkDates(string $startAt, string $endAt): void
    {
        if (new DateTimeImmutable($endAt) <= new DateTimeImmutable($startAt)) {
            throw ValidationException::field('end_at', 'Must be after start_at');
        }
    }

    public static function checkPrice(bool $isPaid, int $priceCents): void
    {
        if ($isPaid !== ($priceCents > 0)) {
            throw ValidationException::field('price_cents', $isPaid ? 'Must be greater than 0 for a paid event' : 'Must be 0 for a free event');
        }
    }
}

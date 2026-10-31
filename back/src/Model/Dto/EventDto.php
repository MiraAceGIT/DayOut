<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

final readonly class EventDto implements JsonSerializable
{
    /** @param list<CategoryDto> $categories */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $description,
        public ?string $coverImageUrl,
        public string $startAt, // ISO 8601 UTC, comme tous les datetime du Caster
        public string $endAt,
        public ?string $venueName,
        public ?string $addressLine,
        public ?string $city,
        public ?string $postalCode,
        public ?float $latitude,
        public ?float $longitude,
        public ?int $capacity, // null = illimitee
        public int $registeredCount,
        public int $priceCents,
        public string $currency,
        public bool $isPaid,
        public ?string $externalTicketUrl,
        public bool $schoolRestricted,
        public array $categories,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'slug'                => $this->slug,
            'description'         => $this->description,
            'cover_image_url'     => $this->coverImageUrl,
            'start_at'            => $this->startAt,
            'end_at'              => $this->endAt,
            'venue_name'          => $this->venueName,
            'address_line'        => $this->addressLine,
            'city'                => $this->city,
            'postal_code'         => $this->postalCode,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'capacity'            => $this->capacity,
            'registered_count'    => $this->registeredCount,
            'price_cents'         => $this->priceCents,
            'currency'            => $this->currency,
            'is_paid'             => $this->isPaid,
            'external_ticket_url' => $this->externalTicketUrl,
            'school_restricted'   => $this->schoolRestricted,
            'categories'          => $this->categories,
        ];
    }
}

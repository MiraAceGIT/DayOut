<?php
declare(strict_types=1);

namespace App\Model\Dto\Event;

use App\Core\ValidationException;
use App\Core\Validator;
use App\Model\DomainServices\EventDomainService;

final readonly class EventUpdateDto
{
    /** @return array<string, string> regles de EventCreateDto, rien d'obligatoire, plus `status` (envoye seul) */
    public static function rules(): array
    {
        return Validator::forUpdate(EventCreateDto::RULES) + ['status' => 'in:published,cancelled'];
    }

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
        public ?string $status = null,
    ) {
    }

    public static function fromArray(array $json): self
    {
        $rules = self::rules();
        $v = Validator::check($json, $rules);
        if ($v === []) {
            throw ValidationException::field('body', 'No writable field (allowed: ' . implode(', ', array_keys($rules)) . ')');
        }
        if (isset($v['status']) && count($v) > 1) {
            throw ValidationException::field('status', 'Must be sent alone');
        }
        if (isset($v['start_at'], $v['end_at'])) {
            EventDomainService::checkDates($v['start_at'], $v['end_at']);
        }
        if (isset($v['is_paid'], $v['price_cents'])) {
            EventDomainService::checkPrice($v['is_paid'], $v['price_cents']);
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
            status: $v['status'] ?? null,
        );
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }
}

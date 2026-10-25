<?php
declare(strict_types=1);

namespace App\Dto;

/**
 * Event tel qu'un client peut l'ecrire (creation). Distinct d'EventDto (lecture) : ni id, ni registered_count,
 * ni school_restricted (deduit de restrictedSchoolId), ni champs serveur (creator_id, status).
 * Construit par EventMapper::fromInput(), qui valide le JSON avec RULES ; ecrit via EventMapper::toRow().
 */
final readonly class EventInputDto
{
    /** Noms du JSON recu (snake_case, comme EventDto) => regles du Validator ; longueurs = colonnes SQL. */
    public const RULES = [
        'title'                => 'required|string|max:180',
        'slug'                 => 'required|string|max:200',
        'description'          => 'required|string',
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
        'currency'             => 'string|min:3|max:3',
        'is_paid'              => 'bool',
        'external_ticket_url'  => 'nullable|url|max:255',
        'restricted_school_id' => 'nullable|int|min:1',
    ];

    /** Valeurs par defaut = DEFAULT des colonnes NOT NULL (price_cents, currency, is_paid). */
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
}

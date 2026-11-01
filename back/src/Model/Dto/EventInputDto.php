<?php
declare(strict_types=1);

namespace App\Model\Dto;

/**
 * Event tel qu'un client peut l'ecrire (creation). Distinct d'EventDto (lecture) : ni id, ni registered_count,
 * ni school_restricted (deduit de restrictedSchoolId), ni champs serveur (creator_id, status).
 */
final readonly class EventInputDto
{
    /** Plafond des colonnes INT UNSIGNED : au-dela, MariaDB refuserait la valeur (1264). */
    private const INT_UNSIGNED_MAX = '4294967295';

    /**
     * Noms du JSON recu (snake_case, comme EventDto) => regles du Validator ; bornes = colonnes SQL.
     * `description` : TEXT = 65 535 octets, soit 16 000 caracteres de 4 octets au pire (utf8mb4).
     */
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
        'capacity'             => 'nullable|int|min:1|max:' . self::INT_UNSIGNED_MAX,
        'price_cents'          => 'int|min:0|max:' . self::INT_UNSIGNED_MAX,
        'currency'             => 'in:EUR',
        'is_paid'              => 'bool',
        'external_ticket_url'  => 'nullable|url|max:255',
        'restricted_school_id' => 'nullable|int|min:1|max:' . self::INT_UNSIGNED_MAX,
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

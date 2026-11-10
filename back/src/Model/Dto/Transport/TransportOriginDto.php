<?php
declare(strict_types=1);

namespace App\Model\Dto\Transport;

use JsonSerializable;

/** Point de depart pre-rempli pour un itineraire (actuellement : l'ecole de l'utilisateur connecte). */
final readonly class TransportOriginDto implements JsonSerializable
{
    public function __construct(
        public string $type, // 'school' pour l'instant ; evoluera si d'autres origines sont ajoutees
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {
    }

    /** @return array{type: string, name: string, latitude: float, longitude: float} */
    public function jsonSerialize(): array
    {
        return [
            'type'      => $this->type,
            'name'      => $this->name,
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}

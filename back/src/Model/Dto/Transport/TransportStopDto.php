<?php
declare(strict_types=1);

namespace App\Model\Dto\Transport;

use JsonSerializable;

/** Arret de transport en commun proche de l'evenement, avec sa distance a vol d'oiseau. */
final readonly class TransportStopDto implements JsonSerializable
{
    /** @param list<string> $lines */
    public function __construct(
        public string $name,
        public string $type, // subway | tram | rail | bus | other
        public float $latitude,
        public float $longitude,
        public int $distanceM, // distance a l'evenement (metres)
        public array $lines,
    ) {
    }

    /** @return array{name: string, type: string, latitude: float, longitude: float, distance_m: int, lines: list<string>} */
    public function jsonSerialize(): array
    {
        return [
            'name'       => $this->name,
            'type'       => $this->type,
            'latitude'   => $this->latitude,
            'longitude'  => $this->longitude,
            'distance_m' => $this->distanceM,
            'lines'      => $this->lines,
        ];
    }
}

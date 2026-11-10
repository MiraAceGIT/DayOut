<?php
declare(strict_types=1);

namespace App\Model\Transport;

/** Representation interne d'un arret, independante de la source (Overpass, PRIM...). */
final readonly class TransportStop
{
    public const TYPES = ['subway', 'tram', 'rail', 'bus', 'other'];

    /** @param list<string> $lines */
    public function __construct(
        public string $name,
        public string $type,
        public float $latitude,
        public float $longitude,
        public int $distanceM,
        public array $lines = [],
    ) {
    }

    public function withDistance(int $distanceM): self
    {
        return new self($this->name, $this->type, $this->latitude, $this->longitude, $distanceM, $this->lines);
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Dto\Transport;

use JsonSerializable;

/**
 * Infos de transport pour un evenement :
 * - origin : depart suggere (ecole de l'utilisateur connecte, sinon null)
 * - stops  : arrets de TC proches de l'evenement
 * - stops_available : false si la source externe (Overpass) n'a pas repondu ; la page reste utilisable,
 *   le front affiche un message neutre plutot qu'une erreur.
 */
final readonly class TransportDto implements JsonSerializable
{
    /** @param list<TransportStopDto> $stops */
    public function __construct(
        public ?TransportOriginDto $origin,
        public array $stops,
        public bool $stopsAvailable,
    ) {
    }

    /** @return array{origin: ?TransportOriginDto, stops: list<TransportStopDto>, stops_available: bool} */
    public function jsonSerialize(): array
    {
        return [
            'origin'          => $this->origin,
            'stops'           => $this->stops,
            'stops_available' => $this->stopsAvailable,
        ];
    }
}

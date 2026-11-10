<?php
declare(strict_types=1);

namespace App\Model\Transport;

/**
 * Source d'arrets de transport en commun. Une implementation = un fournisseur (Overpass, PRIM...).
 *
 * Le service depend de cette interface (DIP) : on peut changer la source ou la mocker en test
 * sans modifier la logique metier.
 */
interface TransportProvider
{
    /**
     * Arrets proches d'un point. Distance deja calculee (vol d'oiseau), tri croissant.
     * Lance TransportProviderException si la source n'est pas joignable ou renvoie un payload invalide.
     *
     * @return list<TransportStop>
     */
    public function findNearbyStops(float $latitude, float $longitude, int $radiusM): array;
}

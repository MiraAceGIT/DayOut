<?php
declare(strict_types=1);

namespace App\Model\Transport;

use RuntimeException;

/**
 * Lancee quand la source externe (Overpass, PRIM...) echoue : reseau down, timeout, 5xx, payload invalide.
 * Pas une BusinessException : c'est degrade, pas une erreur metier. Le service la rattrape et renvoie
 * une reponse neutre (stops_available = false) pour que la page reste utilisable.
 */
final class TransportProviderException extends RuntimeException
{
}

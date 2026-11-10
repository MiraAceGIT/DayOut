<?php
declare(strict_types=1);

namespace App\Model\Transport;

final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    /** Distance a vol d'oiseau en metres (formule de Haversine). Suffit pour trier des arrets < 2 km. */
    public static function haversineDistanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi    = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lng2 - $lng1);

        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }
}

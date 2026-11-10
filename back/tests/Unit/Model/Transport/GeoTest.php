<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Transport;

use App\Model\Transport\Geo;
use Tests\TestCase;

final class GeoTest extends TestCase
{
    public function testHaversineReturnsZeroForSamePoint(): void
    {
        $this->assertSame(0.0, Geo::haversineDistanceM(48.8566, 2.3522, 48.8566, 2.3522));
    }

    public function testHaversineMatchesKnownDistance(): void
    {
        // Centre de Paris (Notre-Dame) -> Opera Garnier : ~2.3 km (reference de nombreux SIG).
        $distance = Geo::haversineDistanceM(48.8530, 2.3498, 48.8720, 2.3316);
        $this->assertTrue($distance > 2200 && $distance < 2500, "Expected ~2.3km, got $distance m");
    }

    public function testHaversineIsSymmetric(): void
    {
        $a = Geo::haversineDistanceM(48.85, 2.35, 48.87, 2.33);
        $b = Geo::haversineDistanceM(48.87, 2.33, 48.85, 2.35);
        $this->assertSame($a, $b);
    }
}

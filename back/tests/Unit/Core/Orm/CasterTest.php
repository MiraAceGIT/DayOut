<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Orm;

use App\Core\Orm\Caster;
use LogicException;
use Tests\TestCase;

final class CasterTest extends TestCase
{
    public function testFloatCastsDecimalStrings(): void
    {
        // PDO MySQL renvoie les DECIMAL en chaine : l'API doit exposer un nombre.
        $row = Caster::apply(['lat' => '48.868900', 'lng' => '-2.341000', 'zero' => '0.000000'], ['lat' => 'float', 'lng' => 'float', 'zero' => 'float'], 'Test');

        $this->assertSame(48.8689, $row['lat']);
        $this->assertSame(-2.341, $row['lng']);
        $this->assertSame(0.0, $row['zero']);
    }

    public function testNullAndMissingColumnsAreLeftUntouched(): void
    {
        $this->assertSame(['lat' => null], Caster::apply(['lat' => null], ['lat' => 'float', 'lng' => 'float'], 'Test'));
    }

    public function testScalarCasts(): void
    {
        $row = Caster::apply(
            ['id' => '7', 'paid' => 0, 'at' => '2026-11-07 20:00:00', 'meta' => '{"a":1}'],
            ['id' => 'int', 'paid' => 'bool', 'at' => 'datetime', 'meta' => 'json'],
            'Test'
        );

        $this->assertSame(['id' => 7, 'paid' => false, 'at' => '2026-11-07T20:00:00+00:00', 'meta' => ['a' => 1]], $row);
    }

    public function testUnknownCastIsADevError(): void
    {
        $e = $this->assertThrows(LogicException::class, fn () => Caster::apply(['x' => '1'], ['x' => 'decimal'], 'Test'));
        $this->assertStringContains('Test::x', $e->getMessage());
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Transport;

use App\Model\Transport\OverpassTransportProvider;
use App\Model\Transport\TransportProviderException;
use RuntimeException;
use Tests\TestCase;

final class OverpassTransportProviderTest extends TestCase
{
    private const CENTER_LAT = 48.8700;
    private const CENTER_LNG = 2.3320;

    /** @param array<string, mixed> $payload */
    private function provider(array $payload): OverpassTransportProvider
    {
        $json = json_encode($payload);
        return new OverpassTransportProvider(
            httpPost: static fn (): string => $json === false ? '' : $json,
        );
    }

    public function testParsesStopsAndClassifiesTypes(): void
    {
        $provider = $this->provider([
            'elements' => [
                ['lat' => 48.8705, 'lon' => 2.3324, 'tags' => ['name' => 'Opera',    'station'       => 'subway']],
                ['lat' => 48.8720, 'lon' => 2.3350, 'tags' => ['name' => 'Gare X',   'railway'       => 'station']],
                ['lat' => 48.8730, 'lon' => 2.3360, 'tags' => ['name' => 'Arret Y',  'highway'       => 'bus_stop', 'ref' => '38;42']],
                ['lat' => 48.8740, 'lon' => 2.3370, 'tags' => ['name' => 'Tram T3',  'railway'       => 'tram_stop']],
                ['lat' => 48.8750, 'lon' => 2.3380, 'tags' => ['name' => 'Inconnu',  'amenity'       => 'parking']],
            ],
        ]);

        $stops = $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 800);

        $this->assertCount(5, $stops);
        $this->assertSame('Opera', $stops[0]->name, 'closest first');
        $this->assertSame('subway', $stops[0]->type);
        $this->assertSame('rail', $stops[1]->type);
        $this->assertSame('bus', $stops[2]->type);
        $this->assertSame(['38', '42'], $stops[2]->lines);
        $this->assertSame('tram', $stops[3]->type);
        $this->assertSame('other', $stops[4]->type);
    }

    public function testDedupesStopsWithSameNameAndType(): void
    {
        $provider = $this->provider([
            'elements' => [
                ['lat' => 48.8705, 'lon' => 2.3324, 'tags' => ['name' => 'Opera', 'station' => 'subway']],
                ['lat' => 48.8706, 'lon' => 2.3325, 'tags' => ['name' => 'Opera', 'station' => 'subway']], // doublon
                ['lat' => 48.8710, 'lon' => 2.3330, 'tags' => ['name' => 'Opera', 'highway' => 'bus_stop']], // type different : garde
            ],
        ]);

        $this->assertCount(2, $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 800));
    }

    public function testIgnoresUnnamedOrMalformedElements(): void
    {
        $provider = $this->provider([
            'elements' => [
                ['lat' => 48.8705, 'lon' => 2.3324, 'tags' => ['station' => 'subway']], // sans name
                ['lat' => 48.8706, 'lon' => 2.3324],                                     // sans tags
                'not-an-array',
                ['lat' => 48.8707, 'lon' => 2.3324, 'tags' => ['name' => 'Valide', 'station' => 'subway']],
            ],
        ]);

        $stops = $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 800);
        $this->assertCount(1, $stops);
        $this->assertSame('Valide', $stops[0]->name);
    }

    public function testThrowsOnMalformedPayload(): void
    {
        $provider = new OverpassTransportProvider(httpPost: static fn (): string => '{"not":"overpass"}');
        $this->assertThrows(TransportProviderException::class,
            fn () => $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 800));
    }

    public function testWrapsHttpFailureAsTransportProviderException(): void
    {
        $provider = new OverpassTransportProvider(httpPost: static function (): string {
            throw new RuntimeException('boom');
        });
        $exception = $this->assertThrows(TransportProviderException::class,
            fn () => $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 800));
        $this->assertStringContains('boom', $exception->getMessage());
    }

    public function testStopsSortedByAscendingDistance(): void
    {
        $provider = $this->provider([
            'elements' => [
                ['lat' => 48.8800, 'lon' => 2.3400, 'tags' => ['name' => 'Loin',   'station' => 'subway']],
                ['lat' => 48.8701, 'lon' => 2.3321, 'tags' => ['name' => 'Proche', 'station' => 'subway']],
            ],
        ]);

        $stops = $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 2000);
        $this->assertSame('Proche', $stops[0]->name);
        $this->assertSame('Loin',   $stops[1]->name);
        $this->assertTrue($stops[0]->distanceM < $stops[1]->distanceM);
    }

    public function testCachesAndReusesStopsBetweenCalls(): void
    {
        $cacheDir = sys_get_temp_dir() . '/dayout-overpass-' . bin2hex(random_bytes(4));
        $calls = 0;
        $payload = json_encode(['elements' => [
            ['lat' => 48.8705, 'lon' => 2.3324, 'tags' => ['name' => 'Opera', 'station' => 'subway']],
        ]]);

        $provider = new OverpassTransportProvider(
            cacheDir: $cacheDir,
            httpPost: static function () use (&$calls, $payload): string {
                $calls++;
                return (string) $payload;
            },
        );

        try {
            $first  = $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 500);
            $second = $provider->findNearbyStops(self::CENTER_LAT, self::CENTER_LNG, 500);

            $this->assertSame(1, $calls, 'provider called once, second time served from cache');
            $this->assertSame($first[0]->name, $second[0]->name);
        } finally {
            array_map('unlink', glob("$cacheDir/*.json") ?: []);
            @rmdir($cacheDir);
        }
    }
}

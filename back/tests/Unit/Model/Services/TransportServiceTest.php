<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Core\Orm\Connection;
use App\Model\Dao\Event\Event;
use App\Model\Dao\School\School;
use App\Model\Exceptions\EventException;
use App\Model\Exceptions\TransportException;
use App\Model\Services\TransportService;
use App\Model\Transport\TransportProvider;
use App\Model\Transport\TransportProviderException;
use App\Model\Transport\TransportStop;
use PDO;
use Tests\TestCase;

/** TransportProvider en memoire : controle le resultat ou l'erreur par test. */
final class FakeTransportProvider implements TransportProvider
{
    /** @var list<TransportStop> */
    public array $stops = [];
    public ?string $failWithMessage = null;
    public int $calls = 0;

    public function findNearbyStops(float $latitude, float $longitude, int $radiusM): array
    {
        $this->calls++;
        if ($this->failWithMessage !== null) {
            throw new TransportProviderException($this->failWithMessage);
        }
        return $this->stops;
    }
}

final class TransportServiceTest extends TestCase
{
    private FakeTransportProvider $provider;
    private TransportService $service;

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, creator_id INTEGER, title TEXT, slug TEXT, description TEXT,
            cover_image_url TEXT, start_at TEXT, end_at TEXT, venue_name TEXT, address_line TEXT, city TEXT, postal_code TEXT,
            latitude REAL, longitude REAL, capacity INTEGER, registered_count INTEGER DEFAULT 0, price_cents INTEGER DEFAULT 0,
            currency TEXT DEFAULT "EUR", is_paid INTEGER DEFAULT 0, external_ticket_url TEXT, school_restricted INTEGER DEFAULT 0,
            restricted_school_id INTEGER, status TEXT, published_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, school_id INTEGER, banned INTEGER DEFAULT 0)');
        $pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT, city TEXT, latitude REAL, longitude REAL, active INTEGER DEFAULT 1)');

        $pdo->exec("INSERT INTO schools (id, name, short_name, city, latitude, longitude, active) VALUES
            (1, 'Institut superieur', 'ISEP', 'Issy', 48.843993, 2.328964, 1),
            (2, 'Sans coord',         NULL,   'X',    NULL,      NULL,     1),
            (3, 'Fermee',             'OUT',  'Y',    48.80,     2.30,     0)");
        $pdo->exec("INSERT INTO users (id, school_id) VALUES (10, 1), (11, 2), (12, 3), (13, NULL)");

        $pdo->exec("INSERT INTO events (id, creator_id, title, slug, description, start_at, end_at, latitude, longitude, status) VALUES
            (1, 10, 'Hackathon', 'hackathon', 'd', '2026-12-01 09:00:00', '2026-12-01 21:00:00', 48.8530, 2.3498, 'published'),
            (2, 10, 'Sans coord',  'sans-coord',  'd', '2026-12-02 09:00:00', '2026-12-02 21:00:00', NULL,    NULL,    'published'),
            (3, 10, 'Brouillon',   'brouillon',   'd', '2026-12-03 09:00:00', '2026-12-03 21:00:00', 48.85,   2.35,    'draft')");

        $connection = new Connection($pdo);
        $this->provider = new FakeTransportProvider();
        $this->service  = new TransportService($this->provider, new Event($connection), new School($connection), radiusM: 800, maxStops: 3);
    }

    public function testReturnsStopsLimitedToMaxSortedByProvider(): void
    {
        $this->provider->stops = [
            new TransportStop('A', 'subway', 48.85, 2.35, 100),
            new TransportStop('B', 'bus',    48.86, 2.36, 200),
            new TransportStop('C', 'rail',   48.87, 2.37, 300),
            new TransportStop('D', 'tram',   48.88, 2.38, 400),
        ];

        $dto = $this->service->forEvent(1, null);

        $this->assertCount(3, $dto->stops, 'maxStops = 3');
        $this->assertSame(['A', 'B', 'C'], array_map(static fn ($s) => $s->name, $dto->stops));
        $this->assertTrue($dto->stopsAvailable);
    }

    public function testOriginIsSchoolOfAuthenticatedUser(): void
    {
        $origin = $this->service->forEvent(1, 10)->origin
            ?? $this->fail('Expected an origin for an authenticated user with a geolocated school');

        $this->assertSame('school', $origin->type);
        $this->assertSame('ISEP', $origin->name, 'short_name preferred');
        $this->assertSame(48.843993, $origin->latitude);
    }

    public function testOriginIsNullWhenAnonymous(): void
    {
        $this->assertSame(null, $this->service->forEvent(1, null)->origin);
    }

    public function testOriginIsNullWhenUserSchoolHasNoCoordinates(): void
    {
        $this->assertSame(null, $this->service->forEvent(1, 11)->origin);
    }

    public function testOriginIsNullWhenUserSchoolIsInactive(): void
    {
        $this->assertSame(null, $this->service->forEvent(1, 12)->origin);
    }

    public function testOriginIsNullWhenUserHasNoSchool(): void
    {
        $this->assertSame(null, $this->service->forEvent(1, 13)->origin);
    }

    public function testDegradesGracefullyWhenProviderFails(): void
    {
        $this->provider->failWithMessage = 'overpass timeout';

        $dto = $this->service->forEvent(1, null);

        $this->assertSame([], $dto->stops);
        $this->assertFalse($dto->stopsAvailable, 'page restera utilisable (CdC D8)');
    }

    public function testThrowsWhenEventNotFound(): void
    {
        $this->assertThrows(EventException::class, fn () => $this->service->forEvent(999, null));
    }

    public function testDraftEventIsNotFound(): void
    {
        // Un brouillon n'est pas expose publiquement, donc findPublishedById le renvoie null.
        $this->assertThrows(EventException::class, fn () => $this->service->forEvent(3, null));
    }

    public function testThrowsWhenEventHasNoCoordinates(): void
    {
        $this->assertThrows(TransportException::class, fn () => $this->service->forEvent(2, null));
    }
}

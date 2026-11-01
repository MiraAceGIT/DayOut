<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Core\Orm\Connection;
use App\Model\Dao\Category;
use App\Model\Dao\Event;
use App\Model\Dto\EventDto;
use App\Model\Exceptions\ErrorKind;
use App\Model\Exceptions\EventException;
use App\Model\Services\EventService;
use DateTimeImmutable;
use PDO;
use Tests\TestCase;

final class EventServiceTest extends TestCase
{
    private EventService $service;

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE events (
            id INTEGER PRIMARY KEY, creator_id INTEGER, title TEXT, slug TEXT, description TEXT, cover_image_url TEXT,
            start_at TEXT, end_at TEXT, venue_name TEXT, address_line TEXT, city TEXT, postal_code TEXT,
            latitude REAL, longitude REAL, capacity INTEGER, registered_count INTEGER DEFAULT 0,
            price_cents INTEGER DEFAULT 0, currency TEXT DEFAULT "EUR", is_paid INTEGER DEFAULT 0,
            external_ticket_url TEXT, school_restricted INTEGER DEFAULT 0, restricted_school_id INTEGER,
            status TEXT, published_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, label TEXT, icon TEXT, active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE event_categories (event_id INTEGER, category_id INTEGER, PRIMARY KEY (event_id, category_id))');
        $pdo->exec("INSERT INTO events (id, title, slug, description, start_at, end_at, status) VALUES
            (1, 'Soiree', 'soiree', 'd', '2026-11-07 20:00:00', '2026-11-08 02:00:00', 'published'),
            (2, 'Match', 'match', 'd', '2026-11-05 18:00:00', '2026-11-05 20:00:00', 'published'),
            (3, 'Brouillon', 'brouillon', 'd', '2026-11-01 10:00:00', '2026-11-01 12:00:00', 'draft'),
            (4, 'Passe', 'passe', 'd', '2026-10-01 10:00:00', '2026-10-01 12:00:00', 'published')");
        $pdo->exec("INSERT INTO categories (id, slug, label) VALUES (1, 'soiree', 'Soiree'), (2, 'sport', 'Sport')");
        $pdo->exec('INSERT INTO event_categories (event_id, category_id) VALUES (1, 1), (3, 2)');

        $connection    = new Connection($pdo);
        $this->service = new EventService(new Event($connection), new Category($connection));
    }

    /**
     * @param list<EventDto> $events
     * @return list<int>
     */
    private static function ids(array $events): array
    {
        return array_map(static fn (EventDto $e): int => $e->id, $events);
    }

    /** @return list<EventDto> */
    private function upcoming(string $from): array
    {
        return $this->service->listUpcoming(new DateTimeImmutable($from), 20, 0);
    }

    public function testListReturnsUpcomingPublishedEventsWithCategories(): void
    {
        $events = $this->upcoming('2026-10-19T00:00:00Z');

        $this->assertSame([2, 1], self::ids($events), 'published, not ended, sorted by start_at');
        $this->assertSame([], $events[0]->categories);
        $this->assertSame('soiree', $events[1]->categories[0]->slug);
    }

    public function testOngoingEventIsListedUntilItEnds(): void
    {
        $this->assertSame([1], self::ids($this->upcoming('2026-11-08T01:00:00Z')));
        $this->assertSame([], $this->upcoming('2026-11-08T02:00:00Z'));
    }

    public function testFromIsComparedInUtc(): void
    {
        // 21:00 a Paris (+01:00) = 20:00 UTC : le match (fin 20:00 UTC) est termine.
        $this->assertSame([1], self::ids($this->upcoming('2026-11-05T21:00:00+01:00')));
    }

    public function testGetPublishedReturnsDtoWithCategories(): void
    {
        $event = $this->service->getPublished(1);

        $this->assertSame(1, $event->id);
        $this->assertSame('{"slug":"soiree","label":"Soiree"}', json_encode($event->categories[0]));
    }

    public function testDraftOrUnknownEventThrowsNotFound(): void
    {
        foreach ([3, 99] as $id) {
            $e = $this->assertThrows(EventException::class, fn () => $this->service->getPublished($id), "event $id");
            $this->assertSame(ErrorKind::NotFound, $e->kind);
            $this->assertSame('EVENT_NOT_FOUND', $e->errorCode);
            $this->assertSame(['id' => $id], $e->details);
        }
    }
}

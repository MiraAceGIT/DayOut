<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Core\Orm\Connection;
use App\Model\Dao\Category;
use App\Model\Dao\Event;
use App\Model\Exceptions\ErrorKind;
use App\Model\Exceptions\EventException;
use App\Model\Services\EventService;
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
        $pdo->exec("INSERT INTO events (id, title, slug, start_at, end_at, status) VALUES
            (1, 'Soiree', 'soiree', '2026-11-07 20:00:00', '2026-11-08 02:00:00', 'published'),
            (2, 'Match', 'match', '2026-11-05 18:00:00', '2026-11-05 20:00:00', 'published'),
            (3, 'Brouillon', 'brouillon', '2026-11-01 10:00:00', '2026-11-01 12:00:00', 'draft')");
        $pdo->exec("INSERT INTO categories (id, slug, label) VALUES (1, 'soiree', 'Soiree'), (2, 'sport', 'Sport')");
        $pdo->exec('INSERT INTO event_categories (event_id, category_id) VALUES (1, 1), (3, 2)');

        $connection    = new Connection($pdo);
        $this->service = new EventService(new Event($connection), new Category($connection));
    }

    public function testListAttachesCategoriesToPublishedEventsOnly(): void
    {
        $events = $this->service->listPublished(20, 0);

        $this->assertSame([2, 1], array_column($events, 'id'), 'published only, sorted by start_at');
        $this->assertSame([], $events[0]['categories']);
        $this->assertSame([['slug' => 'soiree', 'label' => 'Soiree']], $events[1]['categories']);
    }

    public function testGetPublishedAttachesCategories(): void
    {
        $event = $this->service->getPublished(1);

        $this->assertSame([['slug' => 'soiree', 'label' => 'Soiree']], $event['categories'] ?? null);
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

<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Controller\EventController;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Core\Http\ExceptionRenderer;
use App\Model\Dao\Category;
use App\Model\Dao\Creator;
use App\Model\Dao\Event;
use App\Model\Exceptions\EventException;
use App\Model\Services\EventService;
use PDO;
use PDOStatement;
use Tests\TestCase;

final class DetailCountingPdo extends PDO
{
    public int $queries = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        return parent::prepare($query, $options);
    }
}

final class EventDetailServiceTest extends TestCase
{
    private DetailCountingPdo $pdo;
    private EventService $service;

    public function setUp(): void
    {
        $this->pdo = new DetailCountingPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE events (
            id INTEGER PRIMARY KEY, creator_id INTEGER, title TEXT, slug TEXT, description TEXT, cover_image_url TEXT,
            start_at TEXT, end_at TEXT, venue_name TEXT, address_line TEXT, city TEXT, postal_code TEXT,
            latitude REAL, longitude REAL, capacity INTEGER, registered_count INTEGER DEFAULT 0,
            price_cents INTEGER DEFAULT 0, currency TEXT DEFAULT "EUR", is_paid INTEGER DEFAULT 0,
            external_ticket_url TEXT, school_restricted INTEGER DEFAULT 0, restricted_school_id INTEGER,
            status TEXT, published_at TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, school_id INTEGER,
            avatar_url TEXT, bio TEXT, email TEXT, password_hash TEXT, phone TEXT, reliability_score INTEGER)');
        $this->pdo->exec('CREATE TABLE creator_profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, description TEXT, review_notes TEXT)');
        $this->pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT)');
        $this->pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, label TEXT, active INTEGER)');
        $this->pdo->exec('CREATE TABLE event_categories (event_id INTEGER, category_id INTEGER, PRIMARY KEY(event_id, category_id))');
        $this->pdo->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER, status TEXT)');
        $this->pdo->exec("INSERT INTO schools VALUES (1, 'Institut superieur', 'ISEP')");
        $this->pdo->exec("INSERT INTO users VALUES
            (2, 'Camille', 'Martin', 1, 'https://example.test/avatar.jpg', 'Bio publique', 'private@example.test', 'secret-hash', 'secret-phone', 80),
            (3, 'Alex', 'Durand', NULL, NULL, 'Un autre organisateur', 'private2@example.test', 'secret-hash', 'secret-phone', 50)");
        $this->pdo->exec("INSERT INTO creator_profiles VALUES (2, 'Garage ISEP', 'Des projets tech entre etudiants.', 'secret-review')");
        $this->pdo->exec("INSERT INTO events (id, creator_id, title, slug, description, start_at, end_at, status, capacity, registered_count) VALUES
            (1, 2, 'Hackathon', 'hackathon', 'Description', '2026-11-15 09:00:00', '2026-11-15 21:00:00', 'published', 100, 3),
            (2, 3, 'Rencontre', 'rencontre', 'Description', '2026-11-16 09:00:00', '2026-11-16 21:00:00', 'published', NULL, 0),
            (3, 2, 'Draft', 'draft', 'd', '2026-11-16 09:00:00', '2026-11-16 21:00:00', 'draft', NULL, 0),
            (4, 2, 'Archive', 'archive', 'd', '2026-11-16 09:00:00', '2026-11-16 21:00:00', 'archived', NULL, 0),
            (5, 2, 'Annule', 'annule', 'd', '2026-11-16 09:00:00', '2026-11-16 21:00:00', 'cancelled', NULL, 0)");
        $this->pdo->exec("INSERT INTO categories VALUES (1, 'tech', 'Tech', 1), (2, 'bde', 'BDE', 1), (3, 'old', 'Ancienne', 0)");
        $this->pdo->exec('INSERT INTO event_categories VALUES (1, 1), (1, 2), (1, 3), (2, 3)');
        $this->pdo->exec("INSERT INTO registrations VALUES (1, 1, 'registered'), (2, 1, 'attended'), (3, 1, 'no_show'), (4, 1, 'cancelled'), (5, 2, 'cancelled')");
        $connection = new Connection($this->pdo);
        $this->service = new EventService(new Event($connection), new Category($connection), new Creator($connection));
    }

    public function testLoadsEventCategoriesAndCreatorLikeAListItem(): void
    {
        $detail = $this->service->getPublishedDetail(1);
        $this->assertSame(3, $this->pdo->queries, 'event, categories, creator');
        $this->assertSame(1, $detail->event->id);
        $this->assertSame(['bde', 'tech'], array_map(static fn ($c): string => $c->slug, $detail->event->categories));
        $this->assertSame(3, $detail->event->registeredCount, 'denormalized column (kept by triggers), not multiplied by categories');
        $this->assertSame('Garage ISEP', $detail->creator->displayName);
        $this->assertSame('Des projets tech entre etudiants.', $detail->creator->description);
        $this->assertSame('ISEP', $detail->creator->schoolName);
        $this->assertSame('https://example.test/avatar.jpg', $detail->creator->avatarUrl);
    }

    public function testMissingProfileSchoolAndActiveCategoryDoNotHideTheEvent(): void
    {
        $detail = $this->service->getPublishedDetail(2);
        $this->assertSame('Alex Durand', $detail->creator->displayName);
        $this->assertSame('Un autre organisateur', $detail->creator->description);
        $this->assertSame(null, $detail->creator->schoolName);
        $this->assertSame(null, $detail->creator->avatarUrl);
        $this->assertSame([], $detail->event->categories);
        $this->assertSame(0, $detail->event->registeredCount);
        $this->assertSame(null, $detail->event->capacity);
        $this->pdo->exec('DELETE FROM event_categories WHERE event_id = 2');
        $this->assertSame([], $this->service->getPublishedDetail(2)->event->categories, 'no category link at all');
    }

    public function testUnknownAndNonPublishedEventsAreNotReturned(): void
    {
        foreach ([3, 4, 5, 9999] as $id) {
            $e = $this->assertThrows(EventException::class, fn () => $this->service->getPublishedDetail($id));
            $this->assertSame('EVENT_NOT_FOUND', $e->errorCode);
            $this->assertSame(404, ExceptionRenderer::render($e)->status);
        }
    }

    public function testControllerSerializesOnlyPublicData(): void
    {
        $response = (new EventController($this->service))->show(new Request('GET', '/events/1', params: ['id' => '1']));
        $this->assertSame(200, $response->status);
        $json = (string) json_encode($response->payload());
        $data = json_decode($json, true)['data'];
        $this->assertSame(1, $data['id']);
        $this->assertSame(3, $data['registered_count']);
        $this->assertSame(['id', 'display_name', 'description', 'avatar_url', 'school_name'], array_keys($data['creator']));
        foreach (['creator_id', 'status', 'detail_creator_id', 'detail_category_id'] as $internal) {
            $this->assertArrayNotHasKey($internal, $data);
        }
        foreach (['email', 'password_hash', 'phone', 'reliability_score', 'review_notes', 'secret-'] as $private) {
            $this->assertStringNotContains($private, $json);
        }
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Controller\EventController;
use App\Core\Http\RequireAuth;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Model\Dao\Category\Category;
use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Event\Event;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Exceptions\EventException;
use App\Model\Services\EventService;
use LogicException;
use PDO;
use Tests\TestCase;

final class EventWriteServiceTest extends TestCase
{
    private const OWNER = 2;
    private const OTHER_CREATOR = 3;
    private const PARTICIPANT = 7;
    private const BANNED_CREATOR = 8;
    private const ADMIN = 1;

    private PDO $pdo;
    private EventService $service;

    public function setUp(): void
    {
        $pdo = $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE events (
            id INTEGER PRIMARY KEY, creator_id INTEGER, title TEXT, slug TEXT UNIQUE, description TEXT, cover_image_url TEXT,
            start_at TEXT, end_at TEXT, venue_name TEXT, address_line TEXT, city TEXT, postal_code TEXT,
            latitude REAL, longitude REAL, capacity INTEGER, registered_count INTEGER DEFAULT 0,
            price_cents INTEGER DEFAULT 0, currency TEXT DEFAULT "EUR", is_paid INTEGER DEFAULT 0,
            external_ticket_url TEXT, school_restricted INTEGER DEFAULT 0, restricted_school_id INTEGER,
            status TEXT DEFAULT "draft", published_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, school_id INTEGER,
            avatar_url TEXT, bio TEXT, role TEXT, creator_status TEXT, banned INTEGER DEFAULT 0)');
        $pdo->exec('CREATE TABLE creator_profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT)');
        $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, label TEXT, icon TEXT, active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE event_categories (event_id INTEGER, category_id INTEGER, PRIMARY KEY (event_id, category_id))');
        $pdo->exec("INSERT INTO users (id, first_name, last_name, role, creator_status, banned) VALUES
            (1, 'Alice', 'Admin', 'admin', 'none', 0),
            (2, 'BDE', 'ISEP', 'creator', 'approved', 0),
            (3, 'Orga', 'Epita', 'creator', 'approved', 0),
            (7, 'Yo', 'User', 'participant', 'none', 0),
            (8, 'Bob', 'Banni', 'creator', 'approved', 1),
            (9, 'Enzo', 'Pending', 'participant', 'pending', 0)");
        $pdo->exec("INSERT INTO creator_profiles VALUES (2, 'BDE ISEP', NULL)");
        $pdo->exec("INSERT INTO events (id, creator_id, title, slug, description, start_at, end_at, status, is_paid, price_cents, registered_count) VALUES
            (10, 2, 'Soiree', 'soiree', 'd', '2026-11-07 20:00:00', '2026-11-08 02:00:00', 'published', 1, 1500, 0),
            (11, 2, 'Gala', 'gala', 'd', '2026-12-01 20:00:00', '2026-12-02 02:00:00', 'published', 0, 0, 4)");

        $connection = new Connection($pdo);
        $this->service = new EventService(new Event($connection), new Category($connection), new Creator($connection));
    }

    /** @return array<int, mixed> */
    private function column(string $sql): array
    {
        return ($this->pdo->query($sql) ?: throw new LogicException($sql))->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function json(Response $response): array
    {
        return json_decode((string) json_encode(self::payload($response)), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $overrides */
    private static function body(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Afterwork', 'slug' => 'afterwork', 'description' => 'Desc',
            'start_at' => '2026-11-20T18:00:00+01:00', 'end_at' => '2026-11-20T23:00:00+01:00',
        ];
    }

    public function testApprovedCreatorOrAdminCreatesADraftAndGetsItBack(): void
    {
        $event = $this->service->create(EventCreateDto::fromArray(self::body(['id' => 99, 'status' => 'published'])), self::OWNER);

        $this->assertSame('Afterwork', $event->title);
        $this->assertSame('2026-11-20T17:00:00+00:00', $event->startAt, 'stored in UTC');
        $this->assertSame(self::OWNER, $event->creator->id);
        $this->assertSame('BDE ISEP', $event->creator->displayName);
        $this->assertNotSame(99, $event->id, 'client id ignored');
        $this->assertSame(['draft'], $this->column("SELECT status FROM events WHERE id = {$event->id}"), 'status set by the server');
        $this->assertSame([self::OWNER], array_map('intval', $this->column("SELECT creator_id FROM events WHERE id = {$event->id}")));

        $byAdmin = $this->service->create(EventCreateDto::fromArray(self::body(['slug' => 'admin-event'])), self::ADMIN);
        $this->assertSame(self::ADMIN, $byAdmin->creator->id);
    }

    public function testOnlyActiveApprovedCreatorsCanCreate(): void
    {
        foreach ([self::PARTICIPANT, self::BANNED_CREATOR, 9, 404] as $userId) {
            $e = $this->assertThrows(EventException::class,
                fn () => $this->service->create(EventCreateDto::fromArray(self::body()), $userId), "user $userId");
            $this->assertSame('CREATOR_REQUIRED', $e->errorCode);
        }
        $this->assertSame(['2'], array_map('strval', $this->column('SELECT COUNT(*) FROM events')), 'nothing written');
    }

    public function testOwnerUpdatesOnlyTheSentFields(): void
    {
        $event = $this->service->update(10, EventUpdateDto::fromArray(['title' => 'Soiree XXL', 'venue_name' => null]), self::OWNER);

        $this->assertSame('Soiree XXL', $event->title);
        $this->assertSame(null, $event->venueName);
        $this->assertSame(1500, $event->priceCents, 'untouched');
        $this->assertSame('2026-11-07T20:00:00+00:00', $event->startAt, 'untouched');
    }

    public function testCrossFieldRulesUseCurrentValuesForFieldsNotSent(): void
    {
        $cases = [
            'end_at'      => [['end_at' => '2026-11-07T19:00:00Z'], 'Must be after start_at'],
            'price_cents' => [['is_paid' => false], 'Must be 0 for a free event'],
        ];
        foreach ($cases as $field => [$body, $message]) {
            $e = $this->assertThrows(ValidationException::class,
                fn () => $this->service->update(10, EventUpdateDto::fromArray($body), self::OWNER), $field);
            $this->assertSame([$field => $message], $e->errors);
        }

        $free = $this->service->update(10, EventUpdateDto::fromArray(['is_paid' => false, 'price_cents' => 0]), self::OWNER);
        $this->assertSame(false, $free->isPaid);
    }

    public function testOnlyTheOwnerCanUpdateOrDelete(): void
    {
        $update = $this->assertThrows(EventException::class,
            fn () => $this->service->update(10, EventUpdateDto::fromArray(['title' => 'Pirate']), self::OTHER_CREATOR));
        $delete = $this->assertThrows(EventException::class, fn () => $this->service->delete(10, self::OTHER_CREATOR));
        $missing = $this->assertThrows(EventException::class, fn () => $this->service->delete(999, self::OWNER));

        $this->assertSame('EVENT_FORBIDDEN', $update->errorCode);
        $this->assertSame('EVENT_FORBIDDEN', $delete->errorCode);
        $this->assertSame('EVENT_NOT_FOUND', $missing->errorCode);
        $this->assertSame(['Soiree'], $this->column('SELECT title FROM events WHERE id = 10'));
    }

    public function testDeleteRefusesEventsWithRegistrations(): void
    {
        $e = $this->assertThrows(EventException::class, fn () => $this->service->delete(11, self::OWNER));
        $this->assertSame('EVENT_HAS_REGISTRATIONS', $e->errorCode);

        $this->service->delete(10, self::OWNER);
        $this->assertSame([11], array_map('intval', $this->column('SELECT id FROM events')));
    }

    public function testControllerReturns201And204WithTheAuthenticatedUser(): void
    {
        $controller = new EventController($this->service);
        $auth = [RequireAuth::USER_ID => self::OWNER];

        $created = $controller->create(new Request('POST', '/events', rawBody: (string) json_encode(self::body()), attributes: $auth));
        $this->assertSame(201, $created->status);
        $id = self::json($created)['data']['id'];

        $updated = $controller->update(new Request('PATCH', "/events/$id", rawBody: '{"city":"Paris"}', params: ['id' => (string) $id], attributes: $auth));
        $this->assertSame('Paris', self::json($updated)['data']['city']);

        $deleted = $controller->delete(new Request('DELETE', "/events/$id", params: ['id' => (string) $id], attributes: $auth));
        $this->assertSame(204, $deleted->status);
    }
}

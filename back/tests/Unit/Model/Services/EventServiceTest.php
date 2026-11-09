<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Core\Orm\Connection;
use App\Model\Dao\Category\Category;
use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Event\Event;
use App\Model\Dao\Event\EventSearchParams;
use App\Model\Dto\Event\EventDto;
use App\Model\Dto\PageDto;
use App\Model\Exceptions\ErrorKind;
use App\Model\Exceptions\EventException;
use App\Model\Query\EventFindQuery;
use App\Model\Services\EventService;
use DateTimeImmutable;
use PDO;
use Tests\TestCase;

final class EventServiceTest extends TestCase
{
    private EventService $service;
    private PDO $pdo;

    public function setUp(): void
    {
        $pdo = $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE events (
            id INTEGER PRIMARY KEY, creator_id INTEGER, title TEXT, slug TEXT, description TEXT, cover_image_url TEXT,
            start_at TEXT, end_at TEXT, venue_name TEXT, address_line TEXT, city TEXT, postal_code TEXT,
            latitude REAL, longitude REAL, capacity INTEGER, registered_count INTEGER DEFAULT 0,
            price_cents INTEGER DEFAULT 0, currency TEXT DEFAULT "EUR", is_paid INTEGER DEFAULT 0,
            external_ticket_url TEXT, school_restricted INTEGER DEFAULT 0, restricted_school_id INTEGER,
            status TEXT, published_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, label TEXT, icon TEXT, active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, school_id INTEGER,
            avatar_url TEXT, bio TEXT, email TEXT, password_hash TEXT)');
        $pdo->exec('CREATE TABLE creator_profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT)');
        $pdo->exec("INSERT INTO schools VALUES (1, 'Institut superieur', 'ISEP')");
        $pdo->exec("INSERT INTO users VALUES (1, 'Camille', 'Martin', 1, NULL, 'Bio', 'secret@example.test', 'secret-hash'),
            (2, 'Alex', 'Durand', NULL, NULL, NULL, 'secret2@example.test', 'secret-hash')");
        $pdo->exec("INSERT INTO creator_profiles VALUES (1, 'Garage ISEP', 'Projets tech')");
        $pdo->exec('CREATE TABLE event_categories (event_id INTEGER, category_id INTEGER, PRIMARY KEY (event_id, category_id))');
        $pdo->exec("INSERT INTO events (id, creator_id, title, slug, description, start_at, end_at, status) VALUES
            (1, 1, 'Soiree', 'soiree', 'd', '2026-11-07 20:00:00', '2026-11-08 02:00:00', 'published'),
            (2, 2, 'Match', 'match', 'd', '2026-11-05 18:00:00', '2026-11-05 20:00:00', 'published'),
            (3, 1, 'Brouillon', 'brouillon', 'd', '2026-11-01 10:00:00', '2026-11-01 12:00:00', 'draft'),
            (4, 1, 'Passe', 'passe', 'd', '2026-10-01 10:00:00', '2026-10-01 12:00:00', 'published')");
        $pdo->exec("INSERT INTO categories (id, slug, label) VALUES (1, 'soiree', 'Soiree'), (2, 'sport', 'Sport')");
        $pdo->exec('INSERT INTO event_categories (event_id, category_id) VALUES (1, 1), (3, 2)');

        $connection    = new Connection($pdo);
        $this->service = new EventService(new Event($connection), new Category($connection), new Creator($connection));
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
        return $this->page($from, 20, 0)->items;
    }

    /** @return PageDto<EventDto> */
    private function page(string $from, int $limit, int $offset): PageDto
    {
        return $this->service->findMany(new EventSearchParams(endingAfter: new DateTimeImmutable($from), limit: $limit, offset: $offset));
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

    public function testPageUsesLimitOffsetAndReportsTheLastPage(): void
    {
        $from = '2026-10-19T00:00:00Z';
        $first = $this->page($from, 1, 0);
        $last = $this->page($from, 1, 1);
        $empty = $this->page($from, 1, 2);

        $this->assertSame([2], self::ids($first->items));
        $this->assertSame(true, $first->hasMore);
        $this->assertSame(1, $first->limit);
        $this->assertSame(0, $first->offset);
        $this->assertSame([1], self::ids($last->items));
        $this->assertSame(false, $last->hasMore, 'exactly one item on the final page');
        $this->assertSame('soiree', $last->items[0]->categories[0]->slug);
        $this->assertSame([], $empty->items);
        $this->assertSame(false, $empty->hasMore);
    }

    public function testPageOrderIsStableWhenEventsStartAtTheSameTime(): void
    {
        $this->pdo->exec("INSERT INTO events (id, creator_id, title, slug, description, start_at, end_at, status) VALUES
            (5, 1, 'Match bis', 'match-bis', 'd', '2026-11-05 18:00:00', '2026-11-05 20:00:00', 'published'),
            (6, 1, 'Archive', 'archive', 'd', '2026-11-05 18:00:00', '2026-11-05 20:00:00', 'archived')");
        $ids = [];
        for ($offset = 0; $offset < 3; $offset++) {
            $page = $this->page('2026-10-19T00:00:00Z', 1, $offset);
            $ids = array_merge($ids, self::ids($page->items));
            $this->assertSame($offset < 2, $page->hasMore);
        }
        $this->assertSame([2, 5, 1], $ids, 'ties ordered by id; no draft, archive or ended event');
    }

    public function testFromIsComparedInUtc(): void
    {
        // 21:00 a Paris (+01:00) = 20:00 UTC : le match (fin 20:00 UTC) est termine.
        $this->assertSame([1], self::ids($this->upcoming('2026-11-05T21:00:00+01:00')));
    }

    /** @param array<string, mixed> $query */
    private function search(array $query): array
    {
        $params = EventFindQuery::fromQuery(['from' => '2026-10-19T00:00:00Z'] + $query)->toParams();
        return self::ids($this->service->findMany($params)->items);
    }

    public function testSearchWithoutFilterMatchesUpcomingList(): void
    {
        $this->assertSame([2, 1], $this->search([]));
    }

    public function testSearchFilters(): void
    {
        $this->pdo->exec("UPDATE events SET city = 'Paris', venue_name = 'Le 100% Club', is_paid = 1, price_cents = 500,
            capacity = 10, registered_count = 10 WHERE id = 1");

        $this->assertSame([1], $this->search(['q' => 'soir']), 'title');
        $this->assertSame([1], $this->search(['q' => 'par']), 'city');
        $this->assertSame([1], $this->search(['q' => '100%']), '% is literal');
        $this->assertSame([], $this->search(['q' => '1_0']), '_ is literal');
        $this->assertSame([1], $this->search(['city' => 'Paris']));
        $this->assertSame([1], $this->search(['categories' => 'soiree,sport']));
        $this->assertSame([], $this->search(['categories' => 'sport']), 'draft 3 is never exposed');
        $this->assertSame([2], $this->search(['is_free' => 'true']));
        $this->assertSame([1], $this->search(['is_free' => 'false']));
        $this->assertSame([2], $this->search(['available' => 'true']), 'event 1 is full');
        $this->assertSame([1], $this->search(['ids' => '1,3,4']), 'draft and ended ids are dropped');
        $this->assertSame([1], $this->search(['start_from' => '2026-11-07T00:00:00Z', 'start_to' => '2026-11-09T00:00:00Z']));
        $this->assertSame([1, 2], $this->search(['sort_dir' => 'desc']));
        $this->assertSame([2, 1], $this->search(['sort_by' => 'title']));
    }

    public function testEveryListItemCarriesItsPublicCreator(): void
    {
        $page = $this->service->findMany(EventFindQuery::fromQuery(['from' => '2026-10-19T00:00:00Z'])->toParams());

        [$match, $soiree] = $page->items;
        $this->assertSame(2, $match->creator->id);
        $this->assertSame('Alex Durand', $match->creator->displayName, 'no creator profile: user name');
        $this->assertSame(null, $match->creator->schoolName);
        $this->assertSame('Garage ISEP', $soiree->creator->displayName);
        $this->assertSame('ISEP', $soiree->creator->schoolName);

        $json = json_encode($page, JSON_THROW_ON_ERROR);
        $this->assertStringContains('"creator":{"id":1,"display_name":"Garage ISEP"', $json);
        $this->assertStringNotContains('secret', $json, 'no email nor password hash');
    }

    public function testSearchPageReportsHasMore(): void
    {
        $params = EventFindQuery::fromQuery(['from' => '2026-10-19T00:00:00Z', 'limit' => '1'])->toParams();
        $page = $this->service->findMany($params);

        $this->assertSame([2], self::ids($page->items));
        $this->assertSame(1, $page->limit);
        $this->assertSame(true, $page->hasMore);
        $this->assertSame(2, $page->total, 'total ignores limit/offset');

        $last = $this->service->findMany(EventFindQuery::fromQuery(['from' => '2026-10-19T00:00:00Z', 'limit' => '1', 'offset' => '1'])->toParams());
        $this->assertSame([1], self::ids($last->items));
        $this->assertSame(false, $last->hasMore);
        $this->assertSame(2, $last->total);
    }

    public function testGetPublishedReturnsDtoWithCategoriesAndCreator(): void
    {
        $event = $this->service->findOne(1);

        $this->assertSame(1, $event->id);
        $this->assertSame('Garage ISEP', $event->creator->displayName);
        $this->assertSame('{"slug":"soiree","label":"Soiree"}', json_encode($event->categories[0]));
    }

    public function testDraftOrUnknownEventThrowsNotFound(): void
    {
        foreach ([3, 99] as $id) {
            $e = $this->assertThrows(EventException::class, fn () => $this->service->findOne($id), "event $id");
            $this->assertSame(ErrorKind::NotFound, $e->kind);
            $this->assertSame('EVENT_NOT_FOUND', $e->errorCode);
            $this->assertSame(['id' => $id], $e->details);
        }
    }
}

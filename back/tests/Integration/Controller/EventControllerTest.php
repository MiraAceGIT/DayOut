<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

/**
 * Seed : events 1 a 7 published, 8 et 9 archived, 10 draft.
 * Event 6 : sans coordonnees, lie a une seule categorie inactive (doit sortir sans categorie), termine le 2026-10-30.
 * Le seed est date par rapport au 2026-10-19 : les listes passent `from` pour ne pas dependre du jour du test.
 */
final class EventControllerTest extends HttpTestCase
{
    private const SEED_NOW = '2026-10-19T00:00:00Z';

    private function list(string $query = ''): array
    {
        return $this->get('/events?from=' . self::SEED_NOW . ($query === '' ? '' : "&$query"))->json()['data'];
    }

    public function testListReturnsOnlyPublishedEventsSortedByStartDate(): void
    {
        $response = $this->get('/events?from=' . self::SEED_NOW);

        $this->assertStatus(200, $response);
        $data = $response->json()['data'];
        $this->assertSame(20, $data['limit']);
        $this->assertSame(0, $data['offset']);

        $ids = array_column($data['items'], 'id');
        sort($ids);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $ids);

        $starts = array_column($data['items'], 'start_at');
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts, 'items sorted by start_at');
    }

    public function testPagesDoNotOverlap(): void
    {
        $first  = $this->list('limit=3&offset=0');
        $second = $this->list('limit=3&offset=3');

        $this->assertSame(3, $first['limit']);
        $this->assertSame(3, $second['offset']);
        $this->assertCount(3, $first['items']);
        $this->assertCount(3, $second['items']);
        $this->assertSame([], array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')));
    }

    public function testInvalidQueryParametersAre422(): void
    {
        $cases = [
            'limit=500'          => ['limit' => 'Must be at most 100'],
            'limit=0'            => ['limit' => 'Must be at least 1'],
            'limit=abc'          => ['limit' => 'Must be an integer'],
            'offset=-1'          => ['offset' => 'Must be at least 0'],
            'offset=20000'       => ['offset' => 'Must be at most 10000'],
            'from=2026-10-19'    => ['from' => 'Must be an ISO 8601 datetime with timezone'],
        ];
        foreach ($cases as $query => $details) {
            $error = $this->assertError(422, 'VALIDATION_ERROR', $this->get("/events?$query"));
            $this->assertSame($details, $error['details'], $query);
        }
    }

    public function testOffsetPastTheEndGivesAnEmptyPage(): void
    {
        $this->assertSame([], $this->list('offset=10000')['items'], 'never page 1 again (infinite scroll)');
    }

    public function testDefaultListHidesEventsThatAlreadyEnded(): void
    {
        $items = $this->get('/events?limit=100')->json()['data']['items'];

        $now = time();
        foreach ($items as $event) {
            $this->assertTrue(strtotime($event['end_at']) > $now, "event {$event['id']} has ended");
        }
        $this->assertFalse(in_array(6, array_column($items, 'id'), true), 'event 6 ended on 2026-10-30');
    }

    public function testFromKeepsOngoingEventsAndDropsEndedOnes(): void
    {
        // Event 1 : 2026-11-07 20:00 -> 2026-11-08 02:00 UTC ; event 6 : termine le 2026-10-30 23:00 UTC.
        $ids = array_column($this->get('/events?from=2026-11-07T23:00:00Z')->json()['data']['items'], 'id');
        $this->assertTrue(in_array(1, $ids, true), 'ongoing event listed');
        $this->assertFalse(in_array(6, $ids, true), 'ended event hidden');

        // %2B = "+" : 03:00+01:00 = 02:00 UTC, pile la fin de l'event 1, qui n'est plus liste (fin exclue).
        $ids = array_column($this->get('/events?from=2026-11-08T03:00:00%2B01:00')->json()['data']['items'], 'id');
        $this->assertFalse(in_array(1, $ids, true), 'event 1 ended at 02:00 UTC');
        $this->assertTrue(in_array(3, $ids, true), 'later events still listed');
    }

    public function testShowReturnsEventWithCastTypes(): void
    {
        $response = $this->get('/events/1');

        $this->assertStatus(200, $response);
        $event = $response->json()['data'];
        $this->assertSame(1, $event['id']);
        $this->assertSame('soiree-rentree-isep-2026', $event['slug']);
        $this->assertSame(250, $event['capacity']);
        $this->assertSame(false, $event['is_paid']);
        $this->assertSame(true, $event['school_restricted']);
        $this->assertSame('2026-11-07T20:00:00+00:00', $event['start_at']);
    }

    public function testInternalColumnsAreNotExposed(): void
    {
        $events = [$this->get('/events/1')->json()['data'], ...$this->list()['items']];

        foreach ($events as $event) {
            foreach (['creator_id', 'restricted_school_id', 'status', 'published_at', 'created_at', 'updated_at'] as $key) {
                $this->assertFalse(array_key_exists($key, $event), "event {$event['id']} exposes $key");
            }
        }
    }

    public function testShowUnknownEventReturns404(): void
    {
        $error = $this->assertError(404, 'EVENT_NOT_FOUND', $this->get('/events/99999'));
        $this->assertSame('Event not found', $error['message']);
        $this->assertSame(['id' => 99999], $error['details']);
    }

    public function testDraftAndArchivedEventsAreNotExposed(): void
    {
        foreach ([8, 9, 10] as $id) {
            $this->assertError(404, 'EVENT_NOT_FOUND', $this->get("/events/$id"));
        }
    }

    public function testListItemsExposeCoordinatesAndCategories(): void
    {
        $items = $this->list()['items'];

        foreach ($items as $event) {
            $this->assertMapFields($event);
        }
        $byId = array_column($items, null, 'id');
        $this->assertSame(48.8689, $byId[1]['latitude']);
        $this->assertSame(2.341, $byId[1]['longitude']);
        $this->assertSame(
            [['slug' => 'bde', 'label' => 'BDE / Assoc'], ['slug' => 'soiree', 'label' => 'Soiree']],
            $byId[1]['categories'],
            'categories sorted by label'
        );
        $this->assertSame([['slug' => 'concert', 'label' => 'Concert'], ['slug' => 'culture', 'label' => 'Culture']], $byId[4]['categories']);
    }

    public function testShowExposesCoordinatesAndCategories(): void
    {
        $event = $this->get('/events/7')->json()['data'];

        $this->assertMapFields($event);
        $this->assertSame(48.8315, $event['latitude']);
        $this->assertSame(2.2729, $event['longitude']);
        $this->assertSame([['slug' => 'exterieur', 'label' => 'Exterieur'], ['slug' => 'sport', 'label' => 'Sport']], $event['categories']);
    }

    public function testEventWithoutCoordinatesNorActiveCategory(): void
    {
        $fromShow = $this->get('/events/6')->json()['data'];
        $fromList = array_column($this->list()['items'], null, 'id')[6];

        foreach ([$fromShow, $fromList] as $event) {
            $this->assertMapFields($event);
            $this->assertSame(null, $event['latitude']);
            $this->assertSame(null, $event['longitude']);
            $this->assertSame([], $event['categories'], 'inactive category is hidden');
        }
    }

    public function testCategoriesFollowPagination(): void
    {
        $page = $this->list('limit=2&offset=1')['items'];

        $this->assertCount(2, $page);
        foreach ($page as $event) {
            $this->assertSame($event['categories'], $this->get('/events/' . $event['id'])->json()['data']['categories']);
        }
    }

    private function assertMapFields(array $event): void
    {
        $id = 'event ' . ($event['id'] ?? '?');
        foreach (['latitude', 'longitude', 'categories'] as $key) {
            $this->assertArrayHasKey($key, $event, $id);
        }
        $this->assertTrue($event['latitude'] === null || is_float($event['latitude']), "$id latitude float|null");
        $this->assertTrue($event['longitude'] === null || is_float($event['longitude']), "$id longitude float|null");
        $this->assertTrue(is_array($event['categories']) && array_is_list($event['categories']), "$id categories list");
        foreach ($event['categories'] as $category) {
            $this->assertSame(['slug', 'label'], array_keys($category), $id);
            $this->assertTrue(is_string($category['slug']) && is_string($category['label']), $id);
        }
    }
}

<?php
declare(strict_types=1);

namespace Tests\Http;

use Tests\HttpTestCase;

/** Seed : events 1 a 7 published, 8 et 9 archived, 10 draft. */
final class EventsTest extends HttpTestCase
{
    public function testListReturnsOnlyPublishedEventsSortedByStartDate(): void
    {
        $response = $this->get('/api/events');

        $this->assertStatus(200, $response);
        $data = $response->json()['data'];
        $this->assertSame(20, $data['limit']);
        $this->assertSame(0, $data['offset']);

        $ids = array_column($data['items'], 'id');
        sort($ids);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $ids);
        $this->assertSame(['published'], array_values(array_unique(array_column($data['items'], 'status'))));

        $starts = array_column($data['items'], 'start_at');
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts, 'items sorted by start_at');
    }

    public function testPagesDoNotOverlap(): void
    {
        $first  = $this->get('/api/events?limit=3&offset=0')->json()['data'];
        $second = $this->get('/api/events?limit=3&offset=3')->json()['data'];

        $this->assertSame(3, $first['limit']);
        $this->assertSame(3, $second['offset']);
        $this->assertCount(3, $first['items']);
        $this->assertCount(3, $second['items']);
        $this->assertSame([], array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')));
    }

    public function testOutOfRangePaginationFallsBackToDefaults(): void
    {
        $data = $this->get('/api/events?limit=500&offset=-1')->json()['data'];
        $this->assertSame(20, $data['limit']);
        $this->assertSame(0, $data['offset']);

        $data = $this->get('/api/events?limit=abc')->json()['data'];
        $this->assertSame(20, $data['limit']);
    }

    public function testShowReturnsEventWithCastTypes(): void
    {
        $response = $this->get('/api/events/1');

        $this->assertStatus(200, $response);
        $event = $response->json()['data'];
        $this->assertSame(1, $event['id']);
        $this->assertSame('soiree-rentree-isep-2026', $event['slug']);
        $this->assertSame(250, $event['capacity']);
        $this->assertSame(false, $event['is_paid']);
        $this->assertSame(true, $event['school_restricted']);
        $this->assertSame('2026-11-07T20:00:00+00:00', $event['start_at']);
    }

    public function testShowUnknownEventReturns404(): void
    {
        $error = $this->assertError(404, 'NOT_FOUND', $this->get('/api/events/99999'));
        $this->assertSame('Event not found', $error['message']);
        $this->assertSame(['id' => 99999], $error['details']);
    }

    public function testDraftAndArchivedEventsAreNotExposed(): void
    {
        foreach ([8, 9, 10] as $id) {
            $this->assertError(404, 'NOT_FOUND', $this->get("/api/events/$id"));
        }
    }
}

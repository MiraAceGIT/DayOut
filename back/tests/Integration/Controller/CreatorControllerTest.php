<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

final class CreatorControllerTest extends HttpTestCase
{
    public function testListsOnlyApprovedCreatorsWithTheirPublicProfile(): void
    {
        $response = $this->get('/creators');

        $this->assertStatus(200, $response);
        $data = $response->json()['data'];
        $ids = array_column($data['items'], 'id');
        sort($ids);
        $this->assertSame([2, 3, 4], $ids);
        $this->assertSame(3, $data['total']);
        $this->assertSame(false, $data['has_more']);
        foreach ($data['items'] as $creator) {
            $this->assertSame(['id', 'display_name', 'description', 'avatar_url', 'school_name'], array_keys($creator));
        }
    }

    public function testSameCreatorAsOnEvents(): void
    {
        $fromEvent = $this->get('/events/1')->json()['data']['creator'];
        $fromList = $this->get('/creators?ids=' . $fromEvent['id'])->json()['data']['items'];

        $this->assertSame([$fromEvent], $fromList);
    }

    public function testSearchAndPagination(): void
    {
        $this->assertSame([2], array_column($this->get('/creators?q=isep')->json()['data']['items'], 'id'));

        $page = $this->get('/creators?limit=2&offset=0')->json()['data'];
        $this->assertCount(2, $page['items']);
        $this->assertSame(true, $page['has_more']);
        $this->assertSame(3, $page['total']);
    }

    public function testInvalidQueryParametersAre422(): void
    {
        foreach (['sort_by=email', 'ids=1,abc', 'limit=0', 'has_upcoming_events=yes'] as $query) {
            $this->assertError(422, 'VALIDATION_ERROR', $this->get("/creators?$query"));
        }
    }
}

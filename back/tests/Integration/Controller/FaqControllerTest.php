<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

final class FaqControllerTest extends HttpTestCase
{
    public function testPublicFaqLoadsSeededQuestionsWithoutAuthentication(): void
    {
        $response = $this->get('/faq');
        $this->assertStatus(200, $response);
        $page = $response->json()['data'];
        $entries = $page['items'];
        $this->assertSame(10, $page['total']);
        $this->assertSame(false, $page['has_more']);
        $this->assertSame(range(1, 10), array_column($entries, 'id'));
        foreach ($entries as $entry) {
            $this->assertSame(['id', 'question', 'answer', 'sort_order', 'active'], array_keys($entry));
            $this->assertSame(true, $entry['active']);
            $this->assertTrue($entry['question'] !== '' && $entry['answer'] !== '');
        }
    }

    public function testFindOneMatchesTheListAndUnknownEntryIs404(): void
    {
        $first = $this->get('/faq?limit=1')->json()['data']['items'][0];
        $response = $this->get('/faq/' . $first['id']);
        $this->assertStatus(200, $response);
        $this->assertSame($first, $response->json()['data']);
        $this->assertError(404, 'FAQ_NOT_FOUND', $this->get('/faq/999999'));
        $this->assertError(404, 'NOT_FOUND', $this->get('/faq/01'));
    }

    public function testFiltersAndPaginatesBeforeReturningTheTotal(): void
    {
        $response = $this->get('/faq?ids=1,3,5&sort_dir=desc&limit=1&offset=1');
        $this->assertStatus(200, $response);
        $page = $response->json()['data'];
        $this->assertSame([3], array_column($page['items'], 'id'));
        $this->assertSame(3, $page['total']);
        $this->assertSame(true, $page['has_more']);
        $this->assertSame(1, $page['limit']);
        $this->assertSame(1, $page['offset']);

        $search = $this->get('/faq?q=' . rawurlencode('école'));
        $this->assertStatus(200, $search);
        $this->assertTrue($search->json()['data']['total'] > 0);
        $this->assertSame($search->json()['data'], $this->get('/faq?q=ECOLE')->json()['data']);
        $this->assertSame(0, $this->get('/faq?q=aucun-resultat-faq')->json()['data']['total']);
    }

    public function testInvalidFiltersAndUnauthenticatedWritesAreRejected(): void
    {
        foreach (['limit=101', 'offset=-1', 'ids=1,abc', 'sort_by=answer', 'q[]=x'] as $query) {
            $this->assertError(422, 'VALIDATION_ERROR', $this->get('/faq?' . $query));
        }
        foreach ([['POST', '/faq'], ['PATCH', '/faq/1'], ['DELETE', '/faq/1']] as [$method, $path]) {
            $this->assertError(401, 'UNAUTHORIZED', $this->request($method, $path, ['question' => 'Q', 'answer' => 'R']));
        }
    }
}

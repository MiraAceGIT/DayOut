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
        $entries = $response->json()['data'];
        $this->assertSame(range(1, 10), array_column($entries, 'id'));
        foreach ($entries as $entry) {
            $this->assertSame(['id', 'question', 'answer'], array_keys($entry));
            $this->assertTrue($entry['question'] !== '' && $entry['answer'] !== '');
        }
    }
}

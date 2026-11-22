<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

final class SchoolControllerTest extends HttpTestCase
{
    public function testListsTheSeededActiveSchoolsByName(): void
    {
        $response = $this->get('/schools');
        $this->assertStatus(200, $response);
        $schools = $response->json()['data'];
        $this->assertCount(15, $schools);
        $names = array_column($schools, 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $this->assertSame($sorted, $names);
        $isep = array_values(array_filter($schools, static fn (array $s): bool => $s['id'] === 1))[0];
        $this->assertSame(['id', 'name', 'short_name', 'email_domain', 'city'], array_keys($isep));
        $this->assertSame('isep.fr', $isep['email_domain']);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

final class CategoryControllerTest extends HttpTestCase
{
    public function testListsActiveCategoriesWithoutTheDisabledOne(): void
    {
        $response = $this->get('/categories');
        $this->assertStatus(200, $response);
        $categories = $response->json()['data'];
        $this->assertCount(10, $categories);
        $this->assertSame(['slug', 'label'], array_keys($categories[0]));
        $this->assertFalse(in_array('afterwork', array_column($categories, 'slug'), true), 'inactive category hidden');
    }
}

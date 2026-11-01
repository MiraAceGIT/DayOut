<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;

/** Le serveur de test tourne sans DOCS_ENABLED, comme la prod par defaut. */
final class DocsControllerTest extends HttpTestCase
{
    public function testDocsAreNotServedUnlessEnabled(): void
    {
        foreach (['/docs', '/openapi.json'] as $path) {
            $this->assertError(404, 'NOT_FOUND', $this->get($path));
        }
    }
}

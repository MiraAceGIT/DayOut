<?php
declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Core\Config;
use App\Core\Http\Kernel;
use App\Core\Request;
use Tests\Support\AppRouter;
use Tests\Support\TestContainer;
use Tests\TestCase;

final class FaqControllerTest extends TestCase
{
    public function testInvalidIdsAndQueriesFailBeforeConnectingToDatabase(): void
    {
        $kernel = new Kernel(AppRouter::load(TestContainer::create(TestContainer::noDatabase())), new Config());
        foreach (['abc', '0', '-1', '01', '1.5', '9223372036854775808'] as $id) {
            $this->assertSame(404, $kernel->handle(new Request('GET', '/faq/' . $id))->status, $id);
        }
        $this->assertSame(422, $kernel->handle(new Request('GET', '/faq', query: ['limit' => 101]))->status);
    }

    public function testWriteRoutesRequireAuthenticationAndIgnoreForgedUserIds(): void
    {
        $kernel = new Kernel(AppRouter::load(TestContainer::create(TestContainer::noDatabase())), new Config());
        foreach ([['POST', '/faq'], ['PATCH', '/faq/1'], ['DELETE', '/faq/1']] as [$method, $path]) {
            $response = $kernel->handle(new Request($method, $path, query: ['user_id' => 1], headers: ['user-id' => '1']));
            $this->assertSame(401, $response->status);
            $this->assertSame('UNAUTHORIZED', self::payload($response)['error']['code']);
            $this->assertSame('Bearer', $response->header('WWW-Authenticate'));
        }
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\EventController;
use App\Core\Config;
use App\Core\Http\Kernel;
use App\Core\HttpException;
use App\Core\Request;
use Tests\Support\AppRouter;
use Tests\TestCase;

final class EventControllerTest extends TestCase
{
    public function testInvalidIdIs404BeforeConnectingToDatabase(): void
    {
        $controller = new EventController();
        foreach (['', 'abc', '0', '-1', '1.5', '1e2', '01', '+1', ' 1', "1\n", '9223372036854775808', str_repeat('9', 100)] as $id) {
            $error = $this->assertThrows(HttpException::class,
                fn () => $controller->show(new Request('GET', '/events/' . $id, params: ['id' => $id])));
            $this->assertSame(404, $error->status);
        }
    }

    public function testInvalidRoutesReturnRealHttp404(): void
    {
        $kernel = new Kernel(AppRouter::load(), new Config());
        foreach (['abc', '0', '-1', '1.5', '1e2', '01', '9223372036854775808'] as $id) {
            $response = $kernel->handle(new Request('GET', '/events/' . $id));
            $this->assertSame(404, $response->status, $id);
            $this->assertSame('NOT_FOUND', self::payload($response)['error']['code']);
        }
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use App\Core\Request;
use Tests\TestCase;

final class RequestTest extends TestCase
{
    public function testJsonBodyIsDecoded(): void
    {
        $request = new Request('POST', '/', rawBody: '{"title":"Soiree"}');
        $this->assertSame(['title' => 'Soiree'], $request->json());
        $this->assertSame([], (new Request('POST', '/', rawBody: '  '))->json());
    }

    public function testInvalidJsonIs400WithoutParserDetails(): void
    {
        $e = $this->assertThrows(HttpException::class, fn () => (new Request('POST', '/', rawBody: '{"a":'))->json());
        $this->assertSame(400, $e->status);
        $this->assertSame('Invalid JSON body', $e->getMessage());

        $e = $this->assertThrows(HttpException::class, fn () => (new Request('POST', '/', rawBody: '"text"'))->json());
        $this->assertSame('JSON body must be an object or array', $e->getMessage());
    }

    public function testQueryIntFallsBackToDefaultOutsideRange(): void
    {
        $request = new Request('GET', '/', ['limit' => '5', 'big' => '500', 'bad' => 'abc', 'neg' => '-1']);
        $this->assertSame(5, $request->queryInt('limit', 20, 1, 100));
        $this->assertSame(20, $request->queryInt('big', 20, 1, 100));
        $this->assertSame(20, $request->queryInt('bad', 20, 1, 100));
        $this->assertSame(0, $request->queryInt('neg', 0, 0, 100));
        $this->assertSame(20, $request->queryInt('missing', 20, 1, 100));
    }

    public function testHeadersAreCaseInsensitive(): void
    {
        $request = new Request('GET', '/', headers: ['x-csrf-token' => 'abc']);
        $this->assertSame('abc', $request->header('X-CSRF-Token'));
        $this->assertSame(null, $request->header('Authorization'));
    }

    public function testWithAttributeIsImmutable(): void
    {
        $request = new Request('GET', '/');
        $withUser = $request->withAttribute('user', ['id' => 1]);

        $this->assertSame(null, $request->attribute('user'));
        $this->assertSame(['id' => 1], $withUser->attribute('user'));
    }

    public function testFromGlobalsStripsInstallSubdirectoryAndQueryString(): void
    {
        $backup = [$_SERVER, $_GET];
        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'get',
                'REQUEST_URI'    => '/dayout/back/public/events/3/?token=x',
                'SCRIPT_NAME'    => '/dayout/back/public/index.php',
                'HTTP_ORIGIN'    => 'http://front.test',
                'CONTENT_TYPE'   => 'application/json',
            ];
            $_GET = ['token' => 'x'];

            $request = Request::fromGlobals();
            $this->assertSame('GET', $request->method);
            $this->assertSame('/events/3', $request->path);
            $this->assertSame('http://front.test', $request->header('Origin'));
            $this->assertSame('application/json', $request->header('Content-Type'));

            $_SERVER['REQUEST_URI'] = '/health';
            $_SERVER['SCRIPT_NAME'] = '/index.php'; // php -S
            $this->assertSame('/health', Request::fromGlobals()->path);
        } finally {
            [$_SERVER, $_GET] = $backup;
        }
    }
}

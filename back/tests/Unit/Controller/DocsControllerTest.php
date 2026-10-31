<?php
declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\DocsController;
use App\Core\Config;
use App\Core\Http\Kernel;
use App\Core\Request;
use RuntimeException;
use Tests\Support\AppRouter;
use Tests\TestCase;

final class DocsControllerTest extends TestCase
{
    public function testSpecIsServedRawAndUiGetsItsOwnCsp(): void
    {
        $kernel = new Kernel(AppRouter::load(), new Config());

        $spec = $kernel->handle(new Request('GET', '/openapi.json'));
        $this->assertSame(200, $spec->status);
        $this->assertStringContains('application/json', (string) $spec->header('Content-Type'));
        $this->assertSame('3.0.3', json_decode((string) $spec->body(), true)['openapi'], 'not wrapped in data');

        $ui = $kernel->handle(new Request('GET', '/docs'));
        $csp = (string) $ui->header('Content-Security-Policy');
        $this->assertStringContains('text/html', (string) $ui->header('Content-Type'));
        $this->assertStringContains("default-src 'none'", $csp);
        $this->assertStringContains("frame-ancestors 'none'", $csp);
        $this->assertSame('nosniff', $ui->header('X-Content-Type-Options'), 'other security headers still applied');

        // Le hash de la CSP doit couvrir exactement le script inline de la page.
        preg_match('#<script>(.*?)</script>#s', (string) $ui->body(), $m);
        $this->assertStringContains("'sha256-" . base64_encode(hash('sha256', $m[1], true)) . "'", $csp);
    }

    public function testMissingSpecIsAServerError(): void
    {
        $this->assertThrows(RuntimeException::class, fn () => (new DocsController('/nope'))->spec(new Request('GET', '/')));
    }
}

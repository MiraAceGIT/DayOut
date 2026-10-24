<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Config;
use App\Core\Log\Log;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use Throwable;

/** Request -> Response sans effet de bord (ni envoi ni exit) : testable en unitaire. */
final class Kernel
{
    private readonly Cors $cors;

    public function __construct(private readonly Router $router, Config $config)
    {
        $this->cors = new Cors($config->corsOrigins);
    }

    public function handle(Request $request): Response
    {
        try {
            // Preflight CORS : repondu ici, sans passer par les routes.
            $response = $request->method === 'OPTIONS'
                ? Response::noContent()
                : $this->router->dispatch($request);
        } catch (Throwable $e) {
            $response = ExceptionRenderer::render($e);
        }

        // Apres le rendu des erreurs : sans CORS, le navigateur masquerait le body d'erreur au front.
        return $this->cors
            ->apply($request, SecurityHeaders::apply($response))
            ->withHeader('X-Request-Id', Log::requestId());
    }
}

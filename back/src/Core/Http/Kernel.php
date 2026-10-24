<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Config;
use App\Core\Log\Log;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use Throwable;

/**
 * Cycle d'une requete : Request -> Response, sans effet de bord (pas d'envoi, pas d'exit),
 * donc testable en unitaire. public/index.php appelle handle() puis send().
 */
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

        // Apres le rendu des erreurs : CORS et securite sur toutes les reponses.
        return $this->cors
            ->apply($request, SecurityHeaders::apply($response))
            ->withHeader('X-Request-Id', Log::requestId());
    }
}

<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Request;
use App\Core\Response;

/** Origines de CORS_ORIGIN uniquement, jamais `*` (incompatible avec les credentials). */
final class Cors
{
    /** @param array<int, string> $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function apply(Request $request, Response $response): Response
    {
        // Toujours, origine autorisee ou non : sinon un cache partage resservirait une reponse sans CORS a une origine autorisee.
        $response = $response->withHeader('Vary', 'Origin');
        $origin = $request->header('Origin');
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return $response;
        }
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '600')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-Id');
    }
}

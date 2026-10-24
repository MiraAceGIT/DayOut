<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Request;
use App\Core\Response;

/**
 * En-tetes CORS pour les origines de CORS_ORIGIN uniquement (jamais `*` : credentials).
 * Applique par Kernel a toutes les reponses, erreurs comprises : sans ces en-tetes,
 * le navigateur masquerait le body d'erreur au front.
 */
final class Cors
{
    /** @param array<int, string> $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function apply(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return $response;
        }
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '600')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-Id');
    }
}

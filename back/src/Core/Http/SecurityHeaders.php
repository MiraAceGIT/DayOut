<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Response;

/**
 * Seule source des en-tetes de securite : ne pas les dupliquer dans .htaccess.
 * Un en-tete deja pose par la reponse est garde : seule la page de doc (DocsController) assouplit sa CSP.
 */
final class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'DENY',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
        // L'API ne sert que du JSON (hors /docs) : CSP tres restrictive.
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    public static function apply(Response $response): Response
    {
        foreach (self::HEADERS as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }
        return $response;
    }
}

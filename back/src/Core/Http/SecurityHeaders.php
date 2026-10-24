<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Response;

/** En-tetes de securite de toutes les reponses (seule source : plus de doublon dans .htaccess). */
final class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'DENY',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
        // L'API ne sert pas de HTML : CSP tres restrictive.
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    public static function apply(Response $response): Response
    {
        foreach (self::HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}

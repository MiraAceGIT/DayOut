<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Request;
use App\Core\Response;

/**
 * Etape du pipeline d'une route. Peut :
 *   - refuser : throw new HttpException(401, ...) (ou renvoyer une Response) ;
 *   - enrichir la requete : $next($request->withAttribute('user', $user)) ;
 *   - agir apres l'action : $response = $next($request); return $response->withHeader(...).
 */
interface Middleware
{
    /** @param callable(Request): Response $next */
    public function process(Request $request, callable $next): Response;
}

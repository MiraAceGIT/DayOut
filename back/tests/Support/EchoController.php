<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Request;
use App\Core\Response;

/** Action de test : renvoie le parametre {id} et la trace des middlewares. */
final class EchoController
{
    public function show(Request $request): Response
    {
        return Response::json([
            'id'    => $request->param('id'),
            'trace' => $request->attribute('trace', []),
        ]);
    }

    public function broken(Request $request): string
    {
        return 'not a response';
    }
}

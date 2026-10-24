<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use App\Core\Log\Log;
use App\Core\Response;
use PDOException;
use Throwable;

/**
 * Transforme toute exception en Response : aucun try/catch n'est necessaire dans les controllers.
 *   HttpException                  -> son statut, son code, son message, ses en-tetes ;
 *   regle metier d'un trigger SQL  -> traduite par DbErrorMapper (409 / 403 / 422) ;
 *   tout le reste                  -> journalise, 500 generique (jamais de message ni de trace).
 */
final class ExceptionRenderer
{
    public static function render(Throwable $e): Response
    {
        if ($e instanceof PDOException) {
            $e = DbErrorMapper::toHttpException($e) ?? $e;
        }
        if ($e instanceof HttpException) {
            $response = Response::error($e->errorCode, $e->getMessage(), $e->status, $e->details);
            foreach ($e->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
            return $response;
        }

        Log::error('Unhandled exception', ['exception' => $e]);
        return Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
    }
}

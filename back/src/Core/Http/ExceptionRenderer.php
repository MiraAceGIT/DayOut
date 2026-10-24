<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use App\Core\Log\Log;
use App\Core\Response;
use PDOException;
use Throwable;

/** Hors HttpException : 500 generique, jamais de message ni de trace au client. */
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

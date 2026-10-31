<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use App\Core\Log\Log;
use App\Core\Response;
use App\Model\Exceptions\BusinessException;
use App\Model\Exceptions\ErrorKind;
use PDOException;
use Throwable;

/** Hors HttpException et BusinessException : 500 generique, jamais de message ni de trace au client. */
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
        if ($e instanceof BusinessException) {
            Log::info('Business rule violated', ['class' => $e::class, 'code' => $e->errorCode] + $e->details + $e->context);
            return Response::error($e->errorCode, $e->getMessage(), self::statusFor($e->kind), $e->details);
        }

        Log::error('Unhandled exception', ['exception' => $e]);
        return Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
    }

    private static function statusFor(ErrorKind $kind): int
    {
        return match ($kind) {
            ErrorKind::NotFound  => 404,
            ErrorKind::Forbidden => 403,
            ErrorKind::Conflict  => 409,
            ErrorKind::Invalid   => 422,
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class RequireAuth implements Middleware
{
    public const USER_ID = 'user_id';

    public function process(Request $request, callable $next): Response
    {
        if (!is_int($request->attribute(self::USER_ID))) {
            throw HttpException::unauthorized();
        }
        return $next($request);
    }
}

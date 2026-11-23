<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Limite de debit par adresse IP : au-dela de MAX_HITS requetes comptees dans WINDOW_SECONDS, `429 TOO_MANY_REQUESTS`
 * avec `Retry-After`. Une sous-classe par usage (constantes), declaree sur sa route ; COUNT_ONLY_SUCCESS : seules
 * les reponses reussies comptent (ex. inscriptions : un formulaire refuse ne consomme rien).
 */
abstract class RateLimit implements Middleware
{
    protected const BUCKET = '';

    protected const MAX_HITS = 0;

    protected const WINDOW_SECONDS = 3600;

    protected const COUNT_ONLY_SUCCESS = false;

    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = (string) $request->attribute(Request::CLIENT_IP, '');
        if ($this->limiter->isExceeded(static::BUCKET, $ip, static::MAX_HITS, static::WINDOW_SECONDS)) {
            throw new HttpException(429, 'TOO_MANY_REQUESTS', 'Too many requests, try again later',
                ['retry_after_seconds' => static::WINDOW_SECONDS], null, ['Retry-After' => (string) static::WINDOW_SECONDS]);
        }
        if (!static::COUNT_ONLY_SUCCESS) {
            $this->limiter->hit(static::BUCKET, $ip, static::WINDOW_SECONDS);
        }
        $response = $next($request);
        if (static::COUNT_ONLY_SUCCESS && $response->status < 400) {
            $this->limiter->hit(static::BUCKET, $ip, static::WINDOW_SECONDS);
        }
        return $response;
    }
}

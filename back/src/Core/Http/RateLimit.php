<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Limite de debit par client (ClientIp) : au-dela de maxHits() requetes comptees dans WINDOW_SECONDS,
 * `429 TOO_MANY_REQUESTS` avec `Retry-After`. Une sous-classe par usage, declaree sur sa route ; COUNT_ONLY_SUCCESS :
 * seules les reponses reussies restent comptees (ex. inscriptions : un formulaire refuse ne consomme rien).
 */
abstract class RateLimit implements Middleware
{
    protected const BUCKET = '';

    protected const WINDOW_SECONDS = 3600;

    protected const COUNT_ONLY_SUCCESS = false;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly ClientIp $clientIp,
        protected readonly Config $config,
    ) {
    }

    /** Seuil de la fenetre, lu dans la Config (reglable sans redeployer). */
    abstract protected function maxHits(): int;

    public function process(Request $request, callable $next): Response
    {
        $key = $this->clientIp->keyOf($request);

        // Compte AVANT de traiter : des requetes paralleles se voient les unes les autres. Compter apres laisserait
        // passer toute une rafale tant que la premiere est en cours (bcrypt, envoi du mail).
        $hitId = $this->limiter->hit(static::BUCKET, $key, static::WINDOW_SECONDS);
        if ($this->limiter->count(static::BUCKET, $key, static::WINDOW_SECONDS) > $this->maxHits()) {
            $this->limiter->cancel($hitId);
            throw new HttpException(429, 'TOO_MANY_REQUESTS', 'Too many requests, try again later',
                ['retry_after_seconds' => static::WINDOW_SECONDS], null, ['Retry-After' => (string) static::WINDOW_SECONDS]);
        }
        if (!static::COUNT_ONLY_SUCCESS) {
            return $next($request);
        }

        // Place reservee : rendue si la requete echoue (exception metier ou reponse d'erreur).
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->limiter->cancel($hitId);
            throw $e;
        }
        if ($response->status >= 400) {
            $this->limiter->cancel($hitId);
        }
        return $response;
    }
}

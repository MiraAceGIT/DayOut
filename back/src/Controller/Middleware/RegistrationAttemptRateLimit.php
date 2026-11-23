<?php
declare(strict_types=1);

namespace App\Controller\Middleware;

use App\Core\Http\RateLimit;

/**
 * POST /users : toutes les tentatives par client et par heure, refusees ou non
 * (RATE_LIMIT_REGISTRATION_ATTEMPTS_PER_HOUR, 30 par defaut). Sans elle, un robot essaierait des adresses sans fin
 * pour savoir lesquelles ont un compte (409 EMAIL_TAKEN). Plus large que RegistrationRateLimit : un etudiant qui se
 * trompe plusieurs fois dans le formulaire n'est pas bloque.
 */
final class RegistrationAttemptRateLimit extends RateLimit
{
    protected const BUCKET = 'registration_attempt';

    protected function maxHits(): int
    {
        return $this->config->rateLimitRegistrationAttempts;
    }
}

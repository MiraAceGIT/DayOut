<?php
declare(strict_types=1);

namespace App\Controller\Middleware;

use App\Core\Http\RateLimit;

/**
 * POST /users : inscriptions reussies par client et par heure (RATE_LIMIT_REGISTRATIONS_PER_HOUR, 5 par defaut).
 * Chaque inscription envoie un mail : sans limite, un robot pourrait inonder des boites mail d'ecole. Un formulaire
 * refuse ne compte pas ici (voir RegistrationAttemptRateLimit). Par adresse e-mail, la limite est deja de fait :
 * 409 tant que le compte existe (24 h s'il n'est pas confirme).
 */
final class RegistrationRateLimit extends RateLimit
{
    protected const BUCKET = 'registration';

    protected const COUNT_ONLY_SUCCESS = true;

    protected function maxHits(): int
    {
        return $this->config->rateLimitRegistrations;
    }
}

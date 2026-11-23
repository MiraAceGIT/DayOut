<?php
declare(strict_types=1);

namespace App\Controller\Middleware;

use App\Core\Http\RateLimit;

/**
 * POST /users : 5 inscriptions reussies par adresse IP et par heure. Chaque inscription envoie un mail : sans
 * limite, un robot pourrait inonder des boites mail d'ecole. Un formulaire refuse ne compte pas.
 * Par adresse e-mail, la limite est deja de fait : 409 tant que le compte existe (24 h s'il n'est pas confirme).
 */
final class RegistrationRateLimit extends RateLimit
{
    protected const BUCKET = 'registration';

    protected const MAX_HITS = 5;

    protected const WINDOW_SECONDS = 3600;

    protected const COUNT_ONLY_SUCCESS = true;
}

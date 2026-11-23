<?php
declare(strict_types=1);

namespace App\Controller\Middleware;

use App\Core\Http\RateLimit;

/** POST /email-verifications : 30 essais par adresse IP et par heure, reussis ou non (pas d'essais de jetons en masse). */
final class EmailVerificationRateLimit extends RateLimit
{
    protected const BUCKET = 'email_verification';

    protected const MAX_HITS = 30;

    protected const WINDOW_SECONDS = 3600;
}

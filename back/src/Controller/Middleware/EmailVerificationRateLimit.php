<?php
declare(strict_types=1);

namespace App\Controller\Middleware;

use App\Core\Http\RateLimit;

/**
 * POST /email-verifications : essais par client et par heure, reussis ou non (RATE_LIMIT_EMAIL_VERIFICATIONS_PER_HOUR,
 * 30 par defaut) : pas d'essais de jetons en masse.
 */
final class EmailVerificationRateLimit extends RateLimit
{
    protected const BUCKET = 'email_verification';

    protected function maxHits(): int
    {
        return $this->config->rateLimitEmailVerifications;
    }
}

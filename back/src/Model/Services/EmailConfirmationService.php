<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Log\Log;
use App\Core\Mail\Mailer;
use App\Core\Orm\Connection;
use App\Model\Dao\EmailVerification\EmailVerification;
use App\Model\Dao\User\User;
use App\Model\DomainServices\ConfirmationTokenIssuer;
use App\Model\Dto\User\EmailVerificationDto;
use App\Model\Enums\CreatorStatus;
use App\Model\Exceptions\RegistrationException;
use App\Model\Mails\ConfirmationMail;
use DateInterval;

/** Lien de confirmation de l'adresse : envoi (a l'inscription) et confirmation (POST /email-verifications). */
final class EmailConfirmationService
{
    /** Jetons expires gardes une semaine (trace en cas de reclamation), puis purges (purgeExpired). */
    private const PURGE_AFTER = 'P7D';

    public function __construct(
        private readonly EmailVerification $verifications,
        private readonly User $users,
        private readonly Connection $connection,
        private readonly Mailer $mailer,
        private readonly ConfirmationTokenIssuer $tokens,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    /**
     * A appeler dans la transaction de l'appelant : jeton stocke, mail envoye, lien renvoye (pour le mode demo).
     * Mail dans la transaction : avec LogMailer (ecriture locale) c'est instantane, et un echec annule tout. Avec un
     * vrai serveur SMTP, passer par une table d'envoi (outbox) pour ne pas garder la transaction ouverte pendant l'envoi.
     */
    public function sendLink(int $userId, string $email, string $firstName, bool $creator): string
    {
        $token = $this->tokens->issue();
        $this->verifications->createFor($userId, $token->hash, $token->expiresAt);
        $link = $this->config->frontUrl . '/confirm-email?token=' . $token->value;
        $this->mailer->send($email, ConfirmationMail::SUBJECT, ConfirmationMail::body($firstName, $creator, $link));
        return $link;
    }

    /** Purge sans cron, apres chaque inscription et hors de sa transaction : jetons expires depuis plus d'une semaine. */
    public function purgeExpired(): void
    {
        $this->verifications->deleteExpiredBefore($this->clock->now()->sub(new DateInterval(self::PURGE_AFTER)));
    }

    public function confirm(string $token): EmailVerificationDto
    {
        if (!ConfirmationTokenIssuer::isWellFormed($token)) {
            throw RegistrationException::tokenInvalid();
        }
        $verification = $this->verifications->findByTokenHash(ConfirmationTokenIssuer::hash($token))
            ?? throw RegistrationException::tokenInvalid();
        if ($verification['used_at'] !== null) {
            throw RegistrationException::tokenUsed();
        }
        if ($this->tokens->isExpired($verification['expires_at'])) {
            throw RegistrationException::tokenExpired();
        }

        $now = $this->clock->now();
        $this->connection->transaction(function () use ($verification, $now): void {
            if ($this->verifications->markUsed($verification['id'], $now) === 0) {
                throw RegistrationException::tokenUsed();
            }
            $this->users->markEmailVerified($verification['user_id'], $now);
        });

        $creatorStatus = CreatorStatus::from($verification['creator_status']);
        Log::audit('user.email_verified', 'user', $verification['user_id'], 'success', ['creator_status' => $creatorStatus->value]);

        return new EmailVerificationDto(true, $creatorStatus);
    }
}

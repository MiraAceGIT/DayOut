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
use DateInterval;

/** Lien de confirmation de l'adresse : envoi (a l'inscription) et confirmation (POST /email-verifications). */
final class EmailConfirmationService
{
    /** Jetons expires gardes une semaine (trace en cas de reclamation), puis purges a chaque nouvel envoi. */
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
        $this->verifications->deleteExpiredBefore($this->clock->now()->sub(new DateInterval(self::PURGE_AFTER)));
        $link = $this->config->frontUrl . '/confirm-email?token=' . $token->value;
        $this->mailer->send($email, 'Confirme ton adresse e-mail DayOut', self::mail($firstName, $creator, $link));
        return $link;
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

    private static function mail(string $firstName, bool $creator, string $link): string
    {
        $next = $creator
            ? "Ton compte sera actif dès la confirmation. Ta demande de compte créateur sera ensuite examinée par un administrateur :\ntu pourras publier des événements une fois qu'elle sera validée."
            : 'Ton compte sera actif dès la confirmation.';
        return "Bonjour {$firstName},\n\n"
            . "Pour finaliser ton inscription sur DayOut, confirme ton adresse e-mail en ouvrant ce lien (valable 24 h, une seule fois) :\n"
            . "$link\n\n$next\n\n"
            . "Si tu n'es pas à l'origine de cette inscription, ignore ce message.";
    }
}

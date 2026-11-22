<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Config;
use App\Core\Log\Log;
use App\Core\Mail\Mailer;
use App\Core\Orm\Connection;
use App\Core\ValidationException;
use App\Model\Dao\Category\Category;
use App\Model\Dao\CreatorProfile\CreatorProfile;
use App\Model\Dao\EmailVerification\EmailVerification;
use App\Model\Dao\School\School;
use App\Model\Dao\User\User;
use App\Model\Dao\UserInterest\UserInterest;
use App\Model\Dto\User\EmailVerificationDto;
use App\Model\Dto\User\RegisteredUserDto;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;
use App\Model\Exceptions\RegistrationException;
use App\Model\Mappers\UserMapper;
use App\Shared\Password;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** Inscription (participant ou createur) et confirmation de l'adresse par un lien a usage unique. */
final class RegistrationService
{
    /** Duree de validite du lien de confirmation. */
    public const TOKEN_TTL = 'PT24H';

    public function __construct(
        private readonly User $users,
        private readonly School $schools,
        private readonly Category $categories,
        private readonly UserInterest $interests,
        private readonly CreatorProfile $creatorProfiles,
        private readonly EmailVerification $verifications,
        private readonly Connection $connection,
        private readonly Mailer $mailer,
        private readonly Config $config,
    ) {
    }

    public function register(UserCreateDto $dto): RegisteredUserDto
    {
        $this->assertSchoolMatchesEmail($dto);
        $replacedId = $this->replaceableAccount($dto->email);
        $categoryIds = $this->categoryIds($dto->interests);

        $creatorStatus = $dto->isCreator() ? CreatorStatus::Pending : CreatorStatus::None;
        $token = bin2hex(random_bytes(32));
        $link = $this->config->frontUrl . '/confirm-email?token=' . $token;
        $expiresAt = self::now()->add(new DateInterval(self::TOKEN_TTL));

        // Tout ou rien. Le mail part en dernier, avant le COMMIT : s'il echoue, aucun compte n'est cree
        // et l'etudiant peut recommencer (sinon son adresse serait prise sans lien pour la confirmer).
        $id = $this->connection->transaction(function () use ($dto, $replacedId, $categoryIds, $creatorStatus, $token, $link, $expiresAt): int {
            if ($replacedId !== null) {
                // ON DELETE CASCADE : interets, demande createur et anciens jetons partent avec lui.
                $this->users->deleteById($replacedId);
            }
            $id = $this->users->create(UserMapper::toRow($dto), [
                'email_domain'   => self::domainOf($dto->email),
                'password_hash'  => Password::hash($dto->password),
                'role'           => Role::Participant->value,
                'creator_status' => $creatorStatus->value,
            ]);
            $this->interests->addAll($id, $categoryIds);
            if ($dto->isCreator()) {
                $this->creatorProfiles->createFor($id, UserMapper::creatorDisplayName($dto));
            }
            $this->verifications->createFor($id, hash('sha256', $token), $expiresAt);
            $this->mailer->send($dto->email, 'Confirme ton adresse e-mail DayOut', self::confirmationMail($dto, $link));
            return $id;
        });

        Log::audit('user.register', 'user', $id, 'success',
            ['creator_status' => $creatorStatus->value, 'replaced_unverified_user_id' => $replacedId]);

        return UserMapper::toRegisteredDto($id, $dto, $creatorStatus, $this->config->mailDemo ? $link : null);
    }

    public function confirmEmail(string $token): EmailVerificationDto
    {
        // Format verifie avant la base : un lien tronque ou modifie ne coute aucune requete.
        if (preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) {
            throw RegistrationException::tokenInvalid();
        }
        $verification = $this->verifications->findByTokenHash(hash('sha256', $token))
            ?? throw RegistrationException::tokenInvalid();
        if ($verification['used_at'] !== null) {
            throw RegistrationException::tokenUsed();
        }
        $now = self::now();
        if (new DateTimeImmutable($verification['expires_at'], new DateTimeZone('UTC')) <= $now) {
            throw RegistrationException::tokenExpired();
        }

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

    /**
     * Adresse deja utilisee : compte confirme -> EMAIL_TAKEN ; non confirme avec un lien encore valable ->
     * EMAIL_CONFIRMATION_PENDING ; non confirme sans lien valable -> son id, ce compte sera remplace.
     * Sinon une adresse prise par erreur (faute de frappe, inscription au nom d'un autre) le resterait pour toujours.
     */
    private function replaceableAccount(string $email): ?int
    {
        $existing = $this->users->findByEmail($email);
        if ($existing === null) {
            return null;
        }
        if ($existing['email_verified']) {
            throw RegistrationException::emailTaken();
        }
        if ($this->verifications->hasUsableFor($existing['id'], self::now())) {
            throw RegistrationException::confirmationPending();
        }
        return $existing['id'];
    }

    /** Domaine autorise (ecole active) ET egal a celui de l'ecole choisie ; erreurs sous le champ concerne. */
    private function assertSchoolMatchesEmail(UserCreateDto $dto): void
    {
        $school = $this->schools->findActiveById($dto->schoolId)
            ?? throw ValidationException::field('school_id', 'Unknown or inactive school');
        $domain = self::domainOf($dto->email);
        if (!$this->schools->activeDomainExists($domain)) {
            throw ValidationException::field('email', 'This email domain does not belong to an allowed school');
        }
        if ($school['email_domain'] !== $domain) {
            throw ValidationException::field('email', 'This email does not match the selected school');
        }
    }

    /**
     * @param list<string> $slugs
     * @return list<int>
     */
    private function categoryIds(array $slugs): array
    {
        $ids = $this->categories->idsByActiveSlugs($slugs);
        $unknown = array_diff($slugs, array_keys($ids));
        if ($unknown !== []) {
            throw ValidationException::field('interests', 'Unknown category: ' . implode(', ', $unknown));
        }
        return array_values($ids);
    }

    private static function domainOf(string $email): string
    {
        return substr($email, (int) strrpos($email, '@') + 1);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private static function confirmationMail(UserCreateDto $dto, string $link): string
    {
        $next = $dto->isCreator()
            ? "Ton compte sera actif dès la confirmation. Ta demande de compte créateur sera ensuite examinée par un administrateur :\ntu pourras publier des événements une fois qu'elle sera validée."
            : 'Ton compte sera actif dès la confirmation.';
        return "Bonjour {$dto->firstName},\n\n"
            . "Pour finaliser ton inscription sur DayOut, confirme ton adresse e-mail en ouvrant ce lien (valable 24 h, une seule fois) :\n"
            . "$link\n\n$next\n\n"
            . "Si tu n'es pas à l'origine de cette inscription, ignore ce message.";
    }
}

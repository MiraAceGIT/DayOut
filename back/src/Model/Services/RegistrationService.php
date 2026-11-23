<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Config;
use App\Core\Log\Log;
use App\Core\Orm\Connection;
use App\Model\Dao\CreatorProfile\CreatorProfile;
use App\Model\Dao\User\User;
use App\Model\Dao\UserInterest\UserInterest;
use App\Model\DomainServices\CreatorDisplayName;
use App\Model\Dto\User\RegisteredUserDto;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;
use App\Model\Exceptions\RegistrationException;
use App\Model\Mappers\UserMapper;
use App\Model\Policies\RegistrationPolicy;
use App\Shared\Password;
use PDOException;

/**
 * Inscription (participant ou createur) : regles d'acces (RegistrationPolicy), ecriture du compte, puis lien de
 * confirmation (EmailConfirmationService). La confirmation elle-meme est dans EmailConfirmationService.
 */
final class RegistrationService
{
    /** Identifiant renvoye a un robot (champ piege rempli) : aucun compte n'a cet id. */
    public const BOT_TRAP_ID = 0;

    /** SQLSTATE d'une contrainte violee (cle unique comprise), le meme sous MariaDB et SQLite. */
    private const SQLSTATE_INTEGRITY = '23000';

    public function __construct(
        private readonly User $users,
        private readonly UserInterest $interests,
        private readonly CreatorProfile $creatorProfiles,
        private readonly Connection $connection,
        private readonly RegistrationPolicy $policy,
        private readonly EmailConfirmationService $confirmations,
        private readonly Config $config,
    ) {
    }

    public function register(UserCreateDto $dto): RegisteredUserDto
    {
        $creatorStatus = $dto->isCreator() ? CreatorStatus::Pending : CreatorStatus::None;
        if ($dto->botTrapFilled) {
            // Meme reponse qu'une vraie inscription, rien cree ni envoye : le robot ne sait pas qu'il est repere
            // (ni si l'adresse a deja un compte). La limite par client compte cette « inscription ».
            Log::audit('user.register', 'user', null, 'blocked', ['reason' => 'bot_trap']);
            return UserMapper::toRegisteredDto(self::BOT_TRAP_ID, $dto, $creatorStatus, null);
        }

        $this->policy->assertSchoolMatchesEmail($dto);
        $replacedId = $this->policy->replaceableAccount($dto->email);
        $categoryIds = $this->policy->categoryIds($dto->interests);

        try {
            [$id, $link] = $this->create($dto, $replacedId, $categoryIds, $creatorStatus);
        } catch (PDOException $e) {
            // Deux inscriptions simultanees avec la meme adresse : la cle unique refuse la seconde. Meme reponse
            // que si la premiere etait arrivee avant (EMAIL_TAKEN), plutot qu'un CONFLICT generique.
            if ($e->getCode() === self::SQLSTATE_INTEGRITY && $this->users->findByEmail($dto->email) !== null) {
                throw RegistrationException::emailTaken();
            }
            throw $e;
        }
        $this->confirmations->purgeExpired();

        Log::audit('user.register', 'user', $id, 'success',
            ['creator_status' => $creatorStatus->value, 'replaced_unverified_user_id' => $replacedId]);

        // Lien dans la reponse seulement en demo (MAIL_DEMO, refuse au demarrage si APP_ENV=prod).
        return UserMapper::toRegisteredDto($id, $dto, $creatorStatus, $this->config->mailDemo ? $link : null);
    }

    /**
     * Tout ou rien. Le mail part en dernier, avant le COMMIT : s'il echoue, aucun compte n'est cree et l'etudiant peut
     * recommencer (sinon son adresse serait prise sans lien pour la confirmer).
     *
     * @param list<int> $categoryIds
     * @return array{0: int, 1: string} id du compte, lien de confirmation
     */
    private function create(UserCreateDto $dto, ?int $replacedId, array $categoryIds, CreatorStatus $creatorStatus): array
    {
        return $this->connection->transaction(function () use ($dto, $replacedId, $categoryIds, $creatorStatus): array {
            if ($replacedId !== null) {
                // ON DELETE CASCADE : interets, demande createur et anciens jetons partent avec lui.
                $this->users->deleteById($replacedId);
            }
            $id = $this->users->create(UserMapper::toRow($dto), [
                'email_domain'   => RegistrationPolicy::domainOf($dto->email),
                'password_hash'  => Password::hash($dto->password),
                'role'           => Role::Participant->value,
                'creator_status' => $creatorStatus->value,
            ]);
            $this->interests->addAll($id, $categoryIds);
            if ($dto->isCreator()) {
                $this->creatorProfiles->createFor($id, CreatorDisplayName::of($dto->firstName, $dto->lastName, $dto->organizationName));
            }
            return [$id, $this->confirmations->sendLink($id, $dto->email, $dto->firstName, $dto->isCreator())];
        });
    }
}

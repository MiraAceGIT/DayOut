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
use App\Model\Mappers\UserMapper;
use App\Model\Policies\RegistrationPolicy;
use App\Shared\Password;

/**
 * Inscription (participant ou createur) : regles d'acces (RegistrationPolicy), ecriture du compte, puis lien de
 * confirmation (EmailConfirmationService). La confirmation elle-meme est dans EmailConfirmationService.
 */
final class RegistrationService
{
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
        $this->policy->assertSchoolMatchesEmail($dto);
        $replacedId = $this->policy->replaceableAccount($dto->email);
        $categoryIds = $this->policy->categoryIds($dto->interests);
        $creatorStatus = $dto->isCreator() ? CreatorStatus::Pending : CreatorStatus::None;

        // Tout ou rien. Le mail part en dernier, avant le COMMIT : s'il echoue, aucun compte n'est cree
        // et l'etudiant peut recommencer (sinon son adresse serait prise sans lien pour la confirmer).
        [$id, $link] = $this->connection->transaction(function () use ($dto, $replacedId, $categoryIds, $creatorStatus): array {
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

        Log::audit('user.register', 'user', $id, 'success',
            ['creator_status' => $creatorStatus->value, 'replaced_unverified_user_id' => $replacedId]);

        // Lien dans la reponse seulement en demo (MAIL_DEMO, refuse au demarrage si APP_ENV=prod).
        return UserMapper::toRegisteredDto($id, $dto, $creatorStatus, $this->config->mailDemo ? $link : null);
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Policies;

use App\Core\Clock;
use App\Core\ValidationException;
use App\Model\Dao\Category\Category;
use App\Model\Dao\EmailVerification\EmailVerification;
use App\Model\Dao\School\School;
use App\Model\Dao\User\User;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Exceptions\RegistrationException;

/** Qui peut s'inscrire avec quoi : ecole et domaine de l'adresse, adresse deja prise, centres d'interet. */
final class RegistrationPolicy
{
    public function __construct(
        private readonly School $schools,
        private readonly Category $categories,
        private readonly User $users,
        private readonly EmailVerification $verifications,
        private readonly Clock $clock,
    ) {
    }

    /** Domaine autorise (ecole active) ET egal a celui de l'ecole choisie ; erreurs sous le champ concerne. */
    public function assertSchoolMatchesEmail(UserCreateDto $dto): void
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
     * Adresse deja utilisee : compte confirme, ou non confirme avec un lien encore valable -> EMAIL_TAKEN, un seul
     * code pour les deux (l'existence du compte est revelee, comme le demande le ticket, mais pas son etat).
     * Compte non confirme dont le lien a expire -> son id : il sera remplace, sinon une adresse prise par erreur
     * (faute de frappe, inscription au nom d'un autre) le resterait pour toujours.
     */
    public function replaceableAccount(string $email): ?int
    {
        $existing = $this->users->findByEmail($email);
        if ($existing === null) {
            return null;
        }
        if ($existing['email_verified'] || $this->verifications->hasUsableFor($existing['id'], $this->clock->now())) {
            throw RegistrationException::emailTaken();
        }
        return $existing['id'];
    }

    /**
     * Slugs -> identifiants des categories actives ; un slug inconnu ou desactive est refuse (422 sur `interests`).
     *
     * @param list<string> $slugs
     * @return list<int>
     */
    public function categoryIds(array $slugs): array
    {
        $ids = $this->categories->idsByActiveSlugs($slugs);
        $unknown = array_diff($slugs, array_keys($ids));
        if ($unknown !== []) {
            throw ValidationException::field('interests', 'Unknown category: ' . implode(', ', $unknown));
        }
        return array_values($ids);
    }

    /** $email deja en minuscules (UserCreateDto). */
    public static function domainOf(string $email): string
    {
        return substr($email, (int) strrpos($email, '@') + 1);
    }
}

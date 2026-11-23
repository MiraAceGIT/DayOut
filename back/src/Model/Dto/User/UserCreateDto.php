<?php
declare(strict_types=1);

namespace App\Model\Dto\User;

use App\Core\Validator;
use App\Core\ValidationException;
use App\Model\Enums\Role;

/** Corps de POST /users (inscription). La confirmation du mot de passe reste cote navigateur. */
final readonly class UserCreateDto
{
    public const PASSWORD_MIN = 8;

    /** bcrypt ignore ce qui depasse 72 octets : refuse plutot que tronque en silence. */
    public const PASSWORD_MAX_BYTES = 72;

    public const RADIUS_MIN = 1;

    public const RADIUS_MAX = 20;

    public const RADIUS_DEFAULT = 3;

    public const RULES = [
        // Profil demande : un createur reste `participant` tant qu'un admin n'a pas valide sa demande.
        'profile'           => 'required|string|in:' . Role::Participant->value . ',' . Role::Creator->value,
        'first_name'        => 'required|string|max:80',
        'last_name'         => 'required|string|max:80',
        'email'             => 'required|string|max:190|email',
        'school_id'         => 'required|int|min:1|max:' . Validator::INT_UNSIGNED_MAX,
        'password'          => 'required|string|min:' . self::PASSWORD_MIN . '|max:' . self::PASSWORD_MAX_BYTES,
        'terms_accepted'    => 'required|bool',
        'interests'         => 'required|array|max:50',
        'search_radius_km'  => 'int|min:' . self::RADIUS_MIN . '|max:' . self::RADIUS_MAX,
        'organization_name' => 'nullable|string|max:120',
        'individual'        => 'bool',
        // Piege a robots : champ cache du formulaire, qu'un humain ne remplit jamais ; rempli = 422.
        'website'           => 'nullable|string|max:0',
    ];

    /** @param list<string> $interests slugs de categories, sans doublon */
    public function __construct(
        public Role $profile,
        public string $firstName,
        public string $lastName,
        public string $email,
        public int $schoolId,
        public string $password,
        public array $interests,
        public int $searchRadiusKm = self::RADIUS_DEFAULT,
        public ?string $organizationName = null,
        public bool $individual = false,
    ) {
    }

    public static function fromArray(array $json): self
    {
        // E-mail et noms compares sans espaces autour ; l'adresse est stockee en minuscules.
        foreach (['email', 'first_name', 'last_name', 'organization_name'] as $field) {
            if (isset($json[$field]) && is_string($json[$field])) {
                $json[$field] = trim($json[$field]);
            }
        }
        if (isset($json['email']) && is_string($json['email'])) {
            $json['email'] = mb_strtolower($json['email']);
        }

        $clean = Validator::check($json, self::RULES);
        $errors = self::crossFieldErrors($clean);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $creator = $clean['profile'] === Role::Creator->value;
        $individual = $creator && ($clean['individual'] ?? false);

        return new self(
            profile: Role::from($clean['profile']),
            firstName: $clean['first_name'],
            lastName: $clean['last_name'],
            email: $clean['email'],
            schoolId: $clean['school_id'],
            password: $clean['password'],
            interests: array_values(array_unique($clean['interests'])),
            searchRadiusKm: $clean['search_radius_km'] ?? self::RADIUS_DEFAULT,
            organizationName: $creator && !$individual ? $clean['organization_name'] : null,
            individual: $individual,
        );
    }

    public function isCreator(): bool
    {
        return $this->profile === Role::Creator;
    }

    /**
     * Regles sur plusieurs champs ou sur le contenu d'un tableau, apres les regles simples.
     *
     * @param array<string, mixed> $clean
     * @return array<string, string> champ => message
     */
    private static function crossFieldErrors(array $clean): array
    {
        $errors = [];
        $password = $clean['password'];
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            $errors['password'] = 'Must be at most ' . self::PASSWORD_MAX_BYTES . ' bytes';
        } elseif (preg_match('/\p{Lu}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors['password'] = 'Must contain an uppercase letter and a digit';
        }
        if ($clean['terms_accepted'] !== true) {
            $errors['terms_accepted'] = 'The terms of use must be accepted';
        }

        $interests = $clean['interests'];
        if (!array_is_list($interests) || $interests === []
            || array_filter($interests, static fn (mixed $slug): bool => !is_string($slug) || $slug === '') !== []) {
            $errors['interests'] = 'Must be a non-empty list of category slugs';
        }

        $individual = $clean['individual'] ?? false;
        $organization = $clean['organization_name'] ?? null;
        if ($clean['profile'] === Role::Creator->value && !$individual && ($organization === null || $organization === '')) {
            $errors['organization_name'] = 'This field is required for a creator account (or set individual to true)';
        }
        return $errors;
    }
}

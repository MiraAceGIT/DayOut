<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Dto;

use App\Core\ValidationException;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Enums\Role;
use Tests\TestCase;

final class UserCreateDtoTest extends TestCase
{
    /** @return array<string, mixed> corps valide d'un participant */
    public static function body(array $override = []): array
    {
        return $override + [
            'profile' => 'participant', 'first_name' => 'Lina', 'last_name' => 'Martin', 'email' => 'lina.martin@isep.fr',
            'school_id' => 1, 'password' => 'Soiree2026', 'terms_accepted' => true, 'interests' => ['soiree', 'sport'],
            'search_radius_km' => 5,
        ];
    }

    public function testBuildsANormalizedParticipant(): void
    {
        $dto = UserCreateDto::fromArray(self::body([
            'email' => '  Lina.MARTIN@Isep.FR ', 'first_name' => ' Lina ', 'interests' => ['soiree', 'soiree', 'sport'],
            'organization_name' => 'Ignore', 'role' => 'admin', 'creator_status' => 'approved', 'email_verified' => true,
        ]));

        $this->assertSame(Role::Participant, $dto->profile);
        $this->assertFalse($dto->isCreator());
        $this->assertSame('lina.martin@isep.fr', $dto->email);
        $this->assertSame('Lina', $dto->firstName);
        $this->assertSame(['soiree', 'sport'], $dto->interests);
        $this->assertSame(5, $dto->searchRadiusKm);
        $this->assertSame(null, $dto->organizationName, 'only a creator has an organization');
        $this->assertSame(UserCreateDto::RADIUS_DEFAULT, UserCreateDto::fromArray(array_diff_key(self::body(), ['search_radius_km' => 0]))->searchRadiusKm);
    }

    public function testCreatorNeedsAnOrganizationUnlessIndividual(): void
    {
        $error = $this->assertThrows(ValidationException::class, fn () => UserCreateDto::fromArray(self::body(['profile' => 'creator'])));
        $this->assertSame(['organization_name'], array_keys($error->errors));
        $this->assertThrows(ValidationException::class, fn () => UserCreateDto::fromArray(self::body(['profile' => 'creator', 'organization_name' => '  '])));

        $club = UserCreateDto::fromArray(self::body(['profile' => 'creator', 'organization_name' => ' BDE ISEP ']));
        $this->assertTrue($club->isCreator());
        $this->assertSame('BDE ISEP', $club->organizationName);
        $this->assertFalse($club->individual);

        $solo = UserCreateDto::fromArray(self::body(['profile' => 'creator', 'individual' => true, 'organization_name' => 'Ignore']));
        $this->assertTrue($solo->individual);
        $this->assertSame(null, $solo->organizationName);
    }

    public function testPasswordNeedsEightCharactersAnUppercaseAndADigit(): void
    {
        foreach (['Court1', 'sansmajuscule1', 'SANSCHIFFRE', 'Sanschiffre', str_repeat('é', 37) . 'A1'] as $password) {
            $error = $this->assertThrows(ValidationException::class, fn () => UserCreateDto::fromArray(self::body(['password' => $password])), $password);
            $this->assertSame(['password'], array_keys($error->errors), $password);
        }
        $this->assertSame('Été2026xx', UserCreateDto::fromArray(self::body(['password' => 'Été2026xx']))->password, 'accented uppercase counts');
    }

    public function testRejectsEachInvalidField(): void
    {
        foreach ([
            ['profile' => 'admin'], ['profile' => null], ['first_name' => ' '], ['last_name' => str_repeat('n', 81)],
            ['email' => 'pas-un-email'], ['email' => ''], ['school_id' => 0], ['school_id' => 'isep'],
            ['terms_accepted' => false], ['terms_accepted' => 'oui'], ['terms_accepted' => null],
            ['interests' => []], ['interests' => 'soiree'], ['interests' => [1, 2]], ['interests' => ['a' => 'soiree']],
            ['search_radius_km' => 0], ['search_radius_km' => 21], ['search_radius_km' => 2.5],
            ['individual' => 'peut-etre'],
        ] as $input) {
            $error = $this->assertThrows(ValidationException::class, fn () => UserCreateDto::fromArray(self::body($input)), json_encode($input) ?: '');
            $this->assertSame(array_keys($input), array_keys($error->errors), json_encode($input) ?: '');
        }
    }

    public function testRequiresEveryMandatoryField(): void
    {
        $error = $this->assertThrows(ValidationException::class, fn () => UserCreateDto::fromArray([]));
        $required = array_keys(array_filter(UserCreateDto::RULES, static fn (string $r): bool => str_starts_with($r, 'required')));
        $this->assertSame($required, array_keys($error->errors));
    }
}

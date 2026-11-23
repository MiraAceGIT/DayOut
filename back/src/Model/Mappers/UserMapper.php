<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\User\RegisteredUserDto;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;

final class UserMapper
{
    /** @return array<string, mixed> colonnes de `users` saisies par le client (FILLABLE du DAO) */
    public static function toRow(UserCreateDto $dto): array
    {
        return [
            'email'            => $dto->email,
            'first_name'       => $dto->firstName,
            'last_name'        => $dto->lastName,
            'school_id'        => $dto->schoolId,
            'search_radius_km' => $dto->searchRadiusKm,
        ];
    }

    public static function toRegisteredDto(int $id, UserCreateDto $dto, CreatorStatus $creatorStatus, ?string $demoUrl): RegisteredUserDto
    {
        return new RegisteredUserDto(
            id: $id,
            email: $dto->email,
            firstName: $dto->firstName,
            role: Role::Participant,
            creatorStatus: $creatorStatus,
            emailVerified: false,
            demoConfirmationUrl: $demoUrl,
        );
    }
}

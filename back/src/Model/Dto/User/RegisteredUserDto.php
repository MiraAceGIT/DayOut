<?php
declare(strict_types=1);

namespace App\Model\Dto\User;

use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;
use JsonSerializable;

/** Reponse de POST /users : le compte cree, pas encore confirme. */
final readonly class RegisteredUserDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $email,
        public string $firstName,
        public Role $role,
        public CreatorStatus $creatorStatus,
        public bool $emailVerified,
        // Lien de confirmation, seulement en mode demo (MAIL_DEMO=true) ; sinon null.
        public ?string $demoConfirmationUrl = null,
    ) {
    }

    /** @return array{id: int, email: string, first_name: string, role: string, creator_status: string,
     *  email_verified: bool, demo_confirmation_url: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'role' => $this->role->value,
            'creator_status' => $this->creatorStatus->value,
            'email_verified' => $this->emailVerified,
            'demo_confirmation_url' => $this->demoConfirmationUrl,
        ];
    }
}

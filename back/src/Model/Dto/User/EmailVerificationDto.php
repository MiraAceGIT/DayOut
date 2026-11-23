<?php
declare(strict_types=1);

namespace App\Model\Dto\User;

use App\Model\Enums\CreatorStatus;
use JsonSerializable;

/** Reponse de POST /email-verifications : `creator_status` dit au front si une demande createur attend un admin. */
final readonly class EmailVerificationDto implements JsonSerializable
{
    public function __construct(
        public bool $emailVerified,
        public CreatorStatus $creatorStatus,
    ) {
    }

    /** @return array{email_verified: bool, creator_status: string} */
    public function jsonSerialize(): array
    {
        return ['email_verified' => $this->emailVerified, 'creator_status' => $this->creatorStatus->value];
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Dto\School;

use JsonSerializable;

/** Ecole active : `email_domain` permet au formulaire d'inscription de verifier l'adresse saisie. */
final readonly class SchoolDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $shortName,
        public string $emailDomain,
        public ?string $city,
    ) {
    }

    /** @return array{id: int, name: string, short_name: ?string, email_domain: string, city: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'email_domain' => $this->emailDomain,
            'city' => $this->city,
        ];
    }
}

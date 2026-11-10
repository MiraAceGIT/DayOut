<?php
declare(strict_types=1);

namespace App\Model\Dto\Creator;

use JsonSerializable;

final readonly class CreatorDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $description,
        public ?string $avatarUrl,
        public ?string $schoolName,
    ) {
    }

    /** @return array{id: int, display_name: string, description: ?string, avatar_url: ?string, school_name: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'avatar_url' => $this->avatarUrl,
            'school_name' => $this->schoolName,
        ];
    }
}

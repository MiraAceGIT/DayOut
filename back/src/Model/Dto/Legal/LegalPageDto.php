<?php declare(strict_types=1);

namespace App\Model\Dto\Legal;

use DateTimeInterface;
use JsonSerializable;

final readonly class LegalPageDto implements JsonSerializable
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $bodyMd,
        public DateTimeInterface $updatedAt
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'body_md' => $this->bodyMd,
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}

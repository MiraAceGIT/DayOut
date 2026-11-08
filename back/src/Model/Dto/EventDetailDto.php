<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

/** Le detail conserve les champs de la liste et ajoute le profil public du createur. */
final readonly class EventDetailDto implements JsonSerializable
{
    public function __construct(public EventDto $event, public CreatorDto $creator)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->event->jsonSerialize() + ['creator' => $this->creator];
    }
}

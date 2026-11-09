<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

/** Event + profil public du createur : utilise par le detail et par chaque element des listes. */
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

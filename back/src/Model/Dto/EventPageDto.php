<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

final readonly class EventPageDto implements JsonSerializable
{
    /** @param list<EventDetailDto> $items evenements avec leur createur */
    public function __construct(
        public array $items,
        public int $limit,
        public int $offset,
        public bool $hasMore,
        /** Nombre total de resultats (null : non calcule, cf. listUpcomingPage). */
        public ?int $total = null,
    ) {
    }

    /** @return array{items: list<EventDetailDto>, limit: int, offset: int, has_more: bool, total: ?int} */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->hasMore,
            'total' => $this->total,
        ];
    }
}

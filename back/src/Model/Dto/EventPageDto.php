<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

final readonly class EventPageDto implements JsonSerializable
{
    /** @param list<EventDto> $items */
    public function __construct(
        public array $items,
        public int $limit,
        public int $offset,
        public bool $hasMore,
    ) {
    }

    /** @return array{items: list<EventDto>, limit: int, offset: int, has_more: bool} */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->hasMore,
        ];
    }
}

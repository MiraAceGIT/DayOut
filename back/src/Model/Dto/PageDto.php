<?php
declare(strict_types=1);

namespace App\Model\Dto;

use JsonSerializable;

/** @template T of JsonSerializable */
final readonly class PageDto implements JsonSerializable
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $limit,
        public int $offset,
        public bool $hasMore,
        public int $total,
    ) {
    }

    /**
     * @template U of JsonSerializable
     * @param array<int, U> $items
     * @return self<U>
     */
    public static function of(array $items, int $limit, int $offset, int $total): self
    {
        $items = array_values($items);
        return new self($items, $limit, $offset, $offset + count($items) < $total, $total);
    }

    /** @return array{items: list<T>, limit: int, offset: int, has_more: bool, total: int} */
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

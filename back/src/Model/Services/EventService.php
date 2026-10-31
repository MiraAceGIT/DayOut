<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category;
use App\Model\Dao\Event;

/** Lignes d'events avec leurs categories sous la cle `categories`. */
final class EventService
{
    private Event $events;
    private Category $categories;

    public function __construct(?Event $events = null, ?Category $categories = null)
    {
        $this->events     = $events ?? new Event();
        $this->categories = $categories ?? new Category();
    }

    /** @return list<array<string, mixed>> */
    public function listPublished(int $limit, int $offset): array
    {
        return $this->withCategories($this->events->listPublished($limit, $offset));
    }

    /** @return array<string, mixed>|null */
    public function findPublished(int $id): ?array
    {
        $event = $this->events->findPublishedById($id);
        return $event === null ? null : $this->withCategories([$event])[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withCategories(array $rows): array
    {
        $byEvent = $this->categories->listActiveByEvent(array_column($rows, 'id'));
        return array_map(
            static fn (array $row): array => $row + ['categories' => $byEvent[$row['id']] ?? []],
            array_values($rows)
        );
    }
}

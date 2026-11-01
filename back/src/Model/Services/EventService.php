<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category;
use App\Model\Dao\Event;
use App\Model\Dto\EventDto;
use App\Model\Exceptions\EventException;
use App\Model\Mappers\EventMapper;
use DateTimeInterface;

/** Renvoie des DTO : le controller n'a plus qu'a les serialiser. */
final class EventService
{
    private Event $events;
    private Category $categories;

    public function __construct(?Event $events = null, ?Category $categories = null)
    {
        $this->events     = $events ?? new Event();
        $this->categories = $categories ?? new Category();
    }

    /** @return list<EventDto> events publies pas encore termines a `$from` */
    public function listUpcoming(DateTimeInterface $from, int $limit, int $offset): array
    {
        return EventMapper::toDtos($this->withCategories($this->events->listPublishedEndingAfter($from, $limit, $offset)));
    }

    /** @throws EventException brouillon, archive ou inexistant */
    public function getPublished(int $id): EventDto
    {
        $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
        return EventMapper::toDto($this->withCategories([$event])[0]);
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

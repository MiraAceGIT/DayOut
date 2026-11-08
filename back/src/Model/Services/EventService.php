<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category;
use App\Model\Dao\Event;
use App\Model\Dto\EventDto;
use App\Model\Dto\EventDetailDto;
use App\Model\Dto\EventPageDto;
use App\Model\Exceptions\EventException;
use App\Model\Mappers\EventMapper;
use App\Model\Mappers\EventDetailMapper;
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

    /** Un element supplementaire suffit pour savoir si la page suivante existe, sans COUNT global. */
    public function listUpcomingPage(DateTimeInterface $from, int $limit, int $offset): EventPageDto
    {
        $rows = $this->events->listPublishedEndingAfter($from, $limit + 1, $offset);

        return new EventPageDto(
            EventMapper::toDtos($this->withCategories(array_slice($rows, 0, $limit))),
            $limit,
            $offset,
            count($rows) > $limit,
        );
    }

    /** @throws EventException brouillon, archive ou inexistant */
    public function getPublished(int $id): EventDto
    {
        $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
        return EventMapper::toDto($this->withCategories([$event])[0]);
    }

    /** @throws EventException brouillon, annule, archive ou inexistant */
    public function getPublishedDetail(int $id): EventDetailDto
    {
        $row = $this->events->findPublishedDetailById($id) ?? throw EventException::notFound($id);
        return EventDetailMapper::toDto($row);
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

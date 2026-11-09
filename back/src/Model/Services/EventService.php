<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category\Category;
use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Event\Event;
use App\Model\Dao\Event\EventSearchParams;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Dto\PageDto;
use App\Model\Exceptions\EventException;
use App\Model\Mappers\EventMapper;
use LogicException;

/** Renvoie des DTO : le controller n'a plus qu'a les serialiser. */
final class EventService
{
    private Event $events;
    private Category $categories;
    private Creator $creators;

    public function __construct(?Event $events = null, ?Category $categories = null, ?Creator $creators = null)
    {
        $this->events     = $events ?? new Event();
        $this->categories = $categories ?? new Category();
        $this->creators   = $creators ?? new Creator();
    }

    /** @return PageDto<EventDto> */
    public function findMany(EventSearchParams $params): PageDto
    {
        [$rows, $total] = $this->events->findManyPublished($params);

        return PageDto::of($this->toDtos($rows), $params->limit, $params->offset, $total);
    }

    public function findOne(int $id): EventDto
    {
        $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
        return $this->toDtos([$event])[0];
    }

    public function create(EventCreateDto $dto, int $userId): EventDto
    {
        if (!$this->creators->canPublish($userId)) {
            throw EventException::creatorRequired();
        }
        $id = $this->events->create(EventMapper::toRow($dto), ['creator_id' => $userId, 'status' => 'draft']);

        return $this->findForWrite($id);
    }

    public function update(int $id, EventUpdateDto $dto, int $userId): EventDto
    {
        $event = $this->ownedEvent($id, $userId);

        if ($dto->has('start_at') || $dto->has('end_at')) {
            EventCreateDto::checkDates($dto->startAt ?? $event['start_at'], $dto->endAt ?? $event['end_at']);
        }
        if ($dto->has('is_paid') || $dto->has('price_cents')) {
            EventCreateDto::checkPrice($dto->isPaid ?? $event['is_paid'], $dto->priceCents ?? $event['price_cents']);
        }
        $this->events->updateById($id, EventMapper::toUpdateRow($dto));

        return $this->findForWrite($id);
    }

    public function delete(int $id, int $userId): void
    {
        $event = $this->ownedEvent($id, $userId);
        if ($event['registered_count'] > 0) {
            throw EventException::hasRegistrations($id);
        }
        $this->events->deleteById($id);
    }

    /** @return array<string, mixed> */
    private function ownedEvent(int $id, int $userId): array
    {
        $event = $this->events->findForWriteById($id) ?? throw EventException::notFound($id);
        if ($event['creator_id'] !== $userId) {
            throw EventException::notOwner($id);
        }
        return $event;
    }

    private function findForWrite(int $id): EventDto
    {
        $event = $this->events->findForWriteById($id) ?? throw new LogicException("Event $id vanished after write");
        return $this->toDtos([$event])[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<EventDto>
     */
    private function toDtos(array $rows): array
    {
        return EventMapper::toDtos($this->withCreators($this->withCategories($rows)));
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

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withCreators(array $rows): array
    {
        $byId = $this->creators->listPublicByIds(array_column($rows, 'creator_id'));
        return array_map(
            static fn (array $row): array => $row + ($byId[$row['creator_id']]
                ?? throw new LogicException("Creator {$row['creator_id']} of event {$row['id']} not found")),
            $rows
        );
    }
}

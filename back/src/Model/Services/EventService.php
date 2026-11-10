<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Log\Log;
use App\Core\ValidationException;
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
use DateTimeImmutable;
use LogicException;

/** Renvoie des DTO : le controller n'a plus qu'a les serialiser. */
final class EventService
{
    private const LOCKED_WITH_REGISTRATIONS = ['start_at', 'end_at', 'is_paid', 'price_cents', 'currency'];

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
        $this->assertCanManageEvents($userId);
        $id = $this->events->create(EventMapper::toRow($dto), ['creator_id' => $userId, 'status' => 'draft']);
        Log::audit('event.create', 'event', $id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($id);
    }

    public function update(int $id, EventUpdateDto $dto, int $userId): EventDto
    {
        $event = $this->ownedEvent($id, $userId);
        if ($dto->status === 'published') {
            return $this->publish($event, $userId);
        }
        if ($dto->status === 'cancelled') {
            return $this->cancel($event, $userId);
        }
        if (!in_array($event['status'], ['draft', 'published'], true)) {
            throw EventException::invalidStatus($id, $event['status'], 'update');
        }
        $this->assertUnlockedFields($event, $dto);

        if ($dto->has('start_at') || $dto->has('end_at')) {
            EventCreateDto::checkDates($dto->startAt ?? $event['start_at'], $dto->endAt ?? $event['end_at']);
        }
        if ($dto->has('is_paid') || $dto->has('price_cents')) {
            EventCreateDto::checkPrice($dto->isPaid ?? $event['is_paid'], $dto->priceCents ?? $event['price_cents']);
        }
        if ($dto->capacity !== null && $dto->capacity < $event['registered_count']) {
            throw ValidationException::field('capacity', "Must be at least {$event['registered_count']} (current registrations)");
        }
        $this->events->updateById($id, EventMapper::toUpdateRow($dto));
        Log::audit('event.update', 'event', $id, 'success', ['user_id' => $userId, 'fields' => $dto->provided]);

        return $this->findForWrite($id);
    }

    /** @param array<string, mixed> $event */
    private function publish(array $event, int $userId): EventDto
    {
        $id = $event['id'];
        if (new DateTimeImmutable($event['end_at']) <= new DateTimeImmutable()) {
            throw ValidationException::field('end_at', 'Must be in the future to publish');
        }
        $this->transition($id, $event['status'], 'draft', 'published', 'publish', ['published_at' => new DateTimeImmutable()]);
        Log::audit('event.publish', 'event', $id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($id);
    }

    /** @param array<string, mixed> $event */
    private function cancel(array $event, int $userId): EventDto
    {
        $id = $event['id'];
        $this->transition($id, $event['status'], 'published', 'cancelled', 'cancel');
        Log::audit('event.cancel', 'event', $id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($id);
    }

    /** Suppression reservee aux brouillons : publie, l'event a un historique (inscriptions, clics, signalements). */
    public function delete(int $id, int $userId): void
    {
        $event = $this->ownedEvent($id, $userId);
        if ($this->events->deleteDraft($id) === 0) {
            throw EventException::invalidStatus($id, $event['status'], 'delete');
        }
        Log::audit('event.delete', 'event', $id, 'success', ['user_id' => $userId]);
    }

    private function assertCanManageEvents(int $userId): void
    {
        if (!$this->creators->canPublish($userId)) {
            throw EventException::creatorRequired();
        }
    }

    /** @param array<string, mixed> $event */
    private function assertUnlockedFields(array $event, EventUpdateDto $dto): void
    {
        if ($event['status'] !== 'draft' && $dto->has('restricted_school_id')) {
            throw EventException::fieldsLocked($event['id'], ['restricted_school_id'], 'the event is published');
        }
        if ($event['registered_count'] > 0) {
            $locked = array_values(array_intersect(self::LOCKED_WITH_REGISTRATIONS, $dto->provided));
            if ($locked !== []) {
                throw EventException::fieldsLocked($event['id'], $locked, 'the event has registrations');
            }
        }
    }

    /** @param array<string, mixed> $server */
    private function transition(int $id, string $current, string $from, string $to, string $action, array $server = []): void
    {
        if ($current !== $from || $this->events->changeStatus($id, $from, $to, $server) === 0) {
            throw EventException::invalidStatus($id, $current, $action);
        }
    }

    /**
     * Brouillon d'un autre : 404, pour ne pas reveler son existence.
     *
     * @return array<string, mixed>
     */
    private function ownedEvent(int $id, int $userId): array
    {
        $this->assertCanManageEvents($userId);
        $event = $this->events->findForWriteById($id) ?? throw EventException::notFound($id);
        if ($event['creator_id'] !== $userId) {
            throw $event['status'] === 'published' ? EventException::notOwner($id) : EventException::notFound($id);
        }
        return $event;
    }

    private function findForWrite(int $id): EventDto
    {
        $event = $this->events->findForWriteById($id) ?? throw EventException::notFound($id);
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

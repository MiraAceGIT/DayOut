<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Log\Log;
use App\Model\Assemblers\EventAssembler;
use App\Model\Dao\Event\Event;
use App\Model\Dao\Event\EventRecord;
use App\Model\Dao\Event\EventSearchParams;
use App\Model\DomainServices\EventDomainService;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Dto\PageDto;
use App\Model\Enums\EventStatus;
use App\Model\Exceptions\EventException;
use App\Model\Mappers\EventMapper;
use App\Model\Policies\EventPolicy;
use DateTimeImmutable;

/**
 * Orchestration des cas d'usage : droits (EventPolicy), regles (EventDomainService), ecriture (DAO Event),
 * puis reponse en DTO (EventAssembler) ; le controller n'a plus qu'a les serialiser.
 */
final class EventService
{
    public function __construct(
        private readonly Event $events,
        private readonly EventPolicy $policy,
        private readonly EventAssembler $assembler,
    ) {
    }

    /** @return PageDto<EventDto> */
    public function findMany(EventSearchParams $params): PageDto
    {
        [$rows, $total] = $this->events->findManyPublished($params);

        return PageDto::of($this->assembler->toDtos($rows), $params->page, $total);
    }

    public function findOne(int $id): EventDto
    {
        return $this->assembler->toDto($this->events->findPublishedById($id) ?? throw EventException::notFound($id));
    }

    public function create(EventCreateDto $dto, int $userId): EventDto
    {
        $this->policy->assertCanManage($userId);
        $id = $this->events->create(EventMapper::toRow($dto), ['creator_id' => $userId, 'status' => EventStatus::Draft->value]);
        Log::audit('event.create', 'event', $id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($id);
    }

    /** `status` est envoye seul (EventUpdateDto) : c'est soit un changement de statut, soit de champs. */
    public function update(int $id, EventUpdateDto $dto, int $userId): EventDto
    {
        $event = $this->ownedEvent($id, $userId);
        if ($dto->status !== null) {
            return $this->changeStatus($event, EventStatus::from($dto->status), $userId);
        }

        EventDomainService::checkUpdate($event, $dto);
        $this->events->updateById($id, EventMapper::toUpdateRow($dto));
        Log::audit('event.update', 'event', $id, 'success', ['user_id' => $userId, 'fields' => $dto->provided]);

        return $this->findForWrite($id);
    }

    public function delete(int $id, int $userId): void
    {
        $event = $this->ownedEvent($id, $userId);
        // deleteDraft() reverifie le statut dans le WHERE : un event publie entre-temps n'est pas supprime.
        if (!$event->status->isDeletable() || $this->events->deleteDraft($id) === 0) {
            throw EventException::invalidStatus($id, $event->status->value, 'delete');
        }
        Log::audit('event.delete', 'event', $id, 'success', ['user_id' => $userId]);
    }

    /** Publier un brouillon ou annuler un event publie ; atomique (le WHERE reverifie le statut de depart). */
    private function changeStatus(EventRecord $event, EventStatus $to, int $userId): EventDto
    {
        $action = $to === EventStatus::Published ? 'publish' : 'cancel';
        $server = [];
        if ($to === EventStatus::Published) {
            $now = new DateTimeImmutable();
            EventDomainService::checkPublishable($event, $now);
            $server = ['published_at' => $now];
        }

        $from = $to->requiredFrom();
        if ($from === null || $event->status !== $from || $this->events->changeStatus($event->id, $from, $to, $server) === 0) {
            throw EventException::invalidStatus($event->id, $event->status->value, $action);
        }
        Log::audit("event.$action", 'event', $event->id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($event->id);
    }

    private function ownedEvent(int $id, int $userId): EventRecord
    {
        $this->policy->assertCanManage($userId);
        $event = EventRecord::fromRow($this->events->findForWriteById($id) ?? throw EventException::notFound($id));
        $this->policy->assertOwner($event, $userId);

        return $event;
    }

    private function findForWrite(int $id): EventDto
    {
        return $this->assembler->toDto($this->events->findForWriteById($id) ?? throw EventException::notFound($id));
    }
}

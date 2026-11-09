<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Category;
use App\Model\Dao\Creator;
use App\Model\Dao\Event;
use App\Model\Dao\EventSearchParams;
use App\Model\Dto\EventDto;
use App\Model\Dto\EventDetailDto;
use App\Model\Dto\EventPageDto;
use App\Model\Exceptions\EventException;
use App\Model\Mappers\EventMapper;
use App\Model\Mappers\EventDetailMapper;
use DateTimeInterface;
use LogicException;

/** Renvoie des DTO : le controller n'a plus qu'a les serialiser. */
final class EventService
{
    private Event $events;
    private Category $categories;
    private ?Creator $creators;

    public function __construct(?Event $events = null, ?Category $categories = null, ?Creator $creators = null)
    {
        $this->events     = $events ?? new Event();
        $this->categories = $categories ?? new Category();
        $this->creators   = $creators; // paresseux : getPublished n'en a pas besoin
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
            EventDetailMapper::toDtos($this->withCreators($this->withCategories(array_slice($rows, 0, $limit)))),
            $limit,
            $offset,
            count($rows) > $limit,
        );
    }

    /** Recherche filtree : `total` vient du COUNT du DAO, `hasMore` s'en deduit. */
    public function searchPage(EventSearchParams $params): EventPageDto
    {
        [$rows, $total] = $this->events->findManyPublished($params);

        return new EventPageDto(
            EventDetailMapper::toDtos($this->withCreators($this->withCategories($rows))),
            $params->limit,
            $params->offset,
            $params->offset + count($rows) < $total,
            $total,
        );
    }

    /** @throws EventException brouillon, archive ou inexistant */
    public function getPublished(int $id): EventDto
    {
        $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
        return EventMapper::toDto($this->withCategories([$event])[0]);
    }

    /**
     * Meme chargement qu'un element de liste : event, categories actives, createur public.
     *
     * @throws EventException brouillon, annule, archive ou inexistant
     */
    public function getPublishedDetail(int $id): EventDetailDto
    {
        $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
        return EventDetailMapper::toDto($this->withCreators($this->withCategories([$event]))[0]);
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
     * Ajoute les colonnes `detail_*` du createur (format de CreatorMapper) : une requete par page.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withCreators(array $rows): array
    {
        $byId = ($this->creators ??= new Creator())->listPublicByIds(array_column($rows, 'creator_id'));
        return array_map(
            // creator_id NOT NULL + FK RESTRICT : un createur absent est une incoherence de la base.
            static fn (array $row): array => $row + ($byId[$row['creator_id']]
                ?? throw new LogicException("Creator {$row['creator_id']} of event {$row['id']} not found")),
            $rows
        );
    }
}

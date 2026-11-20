<?php
declare(strict_types=1);

namespace App\Model\Assemblers;

use App\Model\Dao\Category\Category;
use App\Model\Dao\Creator\Creator;
use App\Model\Dto\Event\EventDto;
use App\Model\Mappers\EventMapper;
use LogicException;

/**
 * Lignes `events` -> EventDto complets : ajoute categories et createur en une requete chacun
 * pour toute la liste (anti N+1), puis passe la main a EventMapper.
 */
final class EventAssembler
{
    public function __construct(
        private readonly Category $categories,
        private readonly Creator $creators,
    ) {
    }

    /** @param array<string, mixed> $row */
    public function toDto(array $row): EventDto
    {
        return $this->toDtos([$row])[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<EventDto>
     */
    public function toDtos(array $rows): array
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

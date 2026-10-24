<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Mappers\EventMapper;
use App\Orm\Connection;
use App\Orm\Query\QueryBuilder;
use App\Orm\Repository;

/** Acces aux events. Seuls les events `published` sont exposes publiquement. */
final class EventRepository extends Repository
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection, new EventMapper());
    }

    /** Liste des events publies, pagination par limit/offset. */
    public function listPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->published()
            ->orderBy('start_at')
            ->orderBy('id') // departage les egalites : pas de doublon/trou entre pages
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /** Un event par id, uniquement s'il est publie. */
    public function findPublishedById(int $id): ?array
    {
        return $this->published()->where('id', $id)->first();
    }

    /** Scope : events visibles publiquement. */
    private function published(): QueryBuilder
    {
        return $this->query()->where('status', 'published');
    }
}

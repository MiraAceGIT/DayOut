<?php
declare(strict_types=1);

namespace App\Model\Dao;

/** Tri et fenetre d'une liste paginee, communs a toutes les ressources (voir Model::paginate). */
final readonly class PageRequest
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;
    public const MAX_OFFSET = 10000;

    /** @param ?string $sortBy cle publique du tri ; null = tri par defaut de la ressource */
    public function __construct(
        public ?string $sortBy = null,
        public string $sortDir = 'asc',
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = 0,
    ) {
    }
}

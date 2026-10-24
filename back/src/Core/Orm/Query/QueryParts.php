<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

/** Fragments deja valides/quotes par Grammar, sauf $table (brut, quote a la compilation). */
final class QueryParts
{
    public string $table;

    /** @var string[] */
    public array $columns = ['*'];

    /** @var string[] */
    public array $joins = [];

    public WhereClause $wheres;

    /** @var string[] */
    public array $groups = [];

    /** @var string[] */
    public array $orders = [];

    public ?int $limit = null;
    public ?int $offset = null;

    public function __construct(string $table)
    {
        $this->table  = $table;
        $this->wheres = new WhereClause();
    }

    public function __clone()
    {
        // Clone profond : sinon les clones (first(), count()...) partageraient le WhereClause d'origine.
        $this->wheres = clone $this->wheres;
    }
}

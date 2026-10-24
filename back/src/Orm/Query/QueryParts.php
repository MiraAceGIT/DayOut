<?php
declare(strict_types=1);

namespace App\Orm\Query;

/**
 * Etat mutable d'un SELECT en construction. Tous les fragments stockes ici
 * sont deja valides/quotes par Grammar : le Compiler les assemble tels quels.
 */
final class QueryParts
{
    /** Nom de table brut (quote a la compilation). */
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
        // Clone profond : sans ca, first()/count()/... (qui travaillent sur un
        // clone) partageraient le meme WhereClause que la requete d'origine.
        $this->wheres = clone $this->wheres;
    }
}

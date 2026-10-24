<?php
declare(strict_types=1);

namespace App\Orm\Query;

use App\Orm\Connection;
use Closure;
use LogicException;

/**
 * Query builder fluent. Construit l'etat (QueryParts), delegue la
 * compilation au Compiler et l'execution a la Connection.
 *
 * Usage :
 *   Database::orm()->table('events')
 *       ->select('id', 'title')
 *       ->where('status', 'published')
 *       ->where('start_at', '>=', $now)
 *       ->orderBy('start_at')
 *       ->limit(20)
 *       ->get();
 *
 * Securite :
 *  - toutes les valeurs passent par des placeholders `?` (jamais concatenees)
 *  - les identifiants sont valides par Grammar au moment de l'appel
 *  - les methodes *Raw() sont les seules a accepter du SQL brut : ne JAMAIS
 *    y concatener une entree utilisateur (utiliser les bindings).
 */
final class QueryBuilder
{
    use BuildsWhereClauses;
    use ExecutesQueries;

    private QueryParts $parts;
    private ?Connection $connection;
    private ?Closure $hydrator = null;

    /** $connection null = builder imbrique (groupe where), non executable. */
    public function __construct(string $table, ?Connection $connection = null)
    {
        $this->parts      = new QueryParts($table);
        $this->connection = $connection;
    }

    /** Colonnes a selectionner. Accepte `col`, `table.col` et `col AS alias`. */
    public function select(string ...$columns): self
    {
        $this->parts->columns = Grammar::wrapAll($columns ?: ['*']);
        return $this;
    }

    /** Expression brute (COUNT, CONCAT...). Pas de bindings : SQL statique uniquement. */
    public function selectRaw(string $expression): self
    {
        if ($this->parts->columns === ['*']) {
            $this->parts->columns = [];
        }
        $this->parts->columns[] = $expression;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->parts->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            Grammar::joinType($type),
            Grammar::wrap($table),
            Grammar::wrap($first),
            Grammar::operator($operator),
            Grammar::wrap($second)
        );
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function groupBy(string ...$columns): self
    {
        array_push($this->parts->groups, ...Grammar::wrapAll($columns));
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = Grammar::direction($direction);
        $this->parts->orders[] = Grammar::wrap($column) . ' ' . $direction;
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): self
    {
        $this->parts->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->parts->offset = max(0, $offset);
        return $this;
    }

    /** Pagination 1-indexee : forPage(2, 20) -> LIMIT 20 OFFSET 20. */
    public function forPage(int $page, int $perPage): self
    {
        return $this->limit($perPage)->offset((max(1, $page) - 1) * $perPage);
    }

    /** Transformation appliquee a chaque ligne lue (utilise par Repository pour le mapper). */
    public function hydrateWith(?Closure $hydrator): self
    {
        $this->hydrator = $hydrator;
        return $this;
    }

    public function toSql(): string
    {
        return Compiler::select($this->parts);
    }

    public function getBindings(): array
    {
        return $this->parts->wheres->bindings();
    }

    public function __clone()
    {
        $this->parts = clone $this->parts;
    }

    private function connection(): Connection
    {
        if ($this->connection === null) {
            throw new LogicException('This query builder has no connection (nested where group?)');
        }
        return $this->connection;
    }
}

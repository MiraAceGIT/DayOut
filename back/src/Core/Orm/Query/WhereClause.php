<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

/** Fragments deja valides/quotes, enchaines sans parentheses : AND prime sur OR (precedence SQL). */
final class WhereClause
{
    /** @var array<int, array{bool: string, sql: string}> */
    private array $conditions = [];
    private array $bindings = [];

    public function add(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $boolean = strtoupper($boolean) === 'OR' ? 'OR' : 'AND';
        $this->conditions[] = ['bool' => $boolean, 'sql' => $sql];
        // array_values : l'unpacking de cles string est interdit en PHP 8.0.
        array_push($this->bindings, ...array_values($bindings));
        return $this;
    }

    /** Groupe vide = no-op. */
    public function addGroup(WhereClause $group, string $boolean = 'AND'): self
    {
        if ($group->isEmpty()) {
            return $this;
        }
        return $this->add('(' . $group->toSql() . ')', $group->bindings(), $boolean);
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [];
    }

    /** SQL sans le mot-cle WHERE ('' si vide). */
    public function toSql(): string
    {
        $sql = '';
        foreach ($this->conditions as $i => $condition) {
            $sql .= ($i === 0 ? '' : ' ' . $condition['bool'] . ' ') . $condition['sql'];
        }
        return $sql;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }
}

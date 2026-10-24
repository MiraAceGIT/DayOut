<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

/**
 * Liste ordonnee de conditions + leurs bindings. Les fragments recus sont
 * deja valides/quotes : cette classe ne fait que les enchainer.
 * Compile en "a AND b OR (c AND d)" (precedence SQL standard).
 */
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

    /** Ajoute un sous-groupe entre parentheses. Groupe vide = no-op. */
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

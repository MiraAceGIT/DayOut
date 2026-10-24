<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

use Closure;
use InvalidArgumentException;

/** Reserve a QueryBuilder : s'appuie sur sa propriete privee $parts. */
trait BuildsWhereClauses
{
    /**
     * where('col', $value)            -> col = ?
     * where('col', '>=', $value)      -> col >= ?
     * where('col', null)              -> col IS NULL
     * where(fn ($q) => $q->where(...)->orWhere(...))  -> ( ... ) groupe
     * where('col', '>', null)         -> InvalidArgumentException (toujours faux en SQL)
     */
    public function where(Closure|string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof Closure) {
            // Builder sans connexion : il ne sert qu'a collecter les conditions.
            $nested = new self($this->parts->table);
            $column($nested);
            $this->parts->wheres->addGroup($nested->parts->wheres, $boolean);
            return $this;
        }

        // func_num_args et non `$value === null` : where('c', '>', null) doit
        // rester une comparaison a 3 arguments.
        if (func_num_args() === 2) {
            [$operator, $value] = ['=', $operator];
        }
        $operator = Grammar::operator((string) $operator);

        if ($value === null) {
            if ($operator === '=') {
                return $this->addWhere(Grammar::wrap($column) . ' IS NULL', [], $boolean);
            }
            if ($operator === '<>' || $operator === '!=') {
                return $this->addWhere(Grammar::wrap($column) . ' IS NOT NULL', [], $boolean);
            }
            throw new InvalidArgumentException("Cannot compare $column $operator NULL (always false in SQL)");
        }

        return $this->addWhere(Grammar::wrap($column) . " $operator ?", [$value], $boolean);
    }

    public function orWhere(Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof Closure || func_num_args() === 3) {
            return $this->where($column, $operator, $value, 'OR');
        }
        return $this->where($column, '=', $operator, 'OR');
    }

    public function whereIn(string $column, array $values, bool $not = false): self
    {
        if ($values === []) {
            // IN () est invalide en SQL : on renvoie toujours faux (ou vrai pour NOT IN)
            return $not ? $this : $this->addWhere('0 = 1', []);
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $keyword = $not ? 'NOT IN' : 'IN';
        return $this->addWhere(Grammar::wrap($column) . " $keyword ($placeholders)", array_values($values));
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, true);
    }

    public function whereNull(string $column): self
    {
        return $this->addWhere(Grammar::wrap($column) . ' IS NULL', []);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addWhere(Grammar::wrap($column) . ' IS NOT NULL', []);
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->addWhere(Grammar::wrap($column) . ' BETWEEN ? AND ?', [$min, $max]);
    }

    /**
     * SQL brut : ne JAMAIS y concatener d'entree utilisateur, passer par $bindings.
     * Toujours entoure de parentheses : un OR dans $sql ne peut pas annuler les autres conditions.
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        return $this->addWhere('(' . $sql . ')', $bindings, $boolean);
    }

    private function addWhere(string $sql, array $bindings, string $boolean = 'AND'): self
    {
        $this->parts->wheres->add($sql, $bindings, $boolean);
        return $this;
    }
}
